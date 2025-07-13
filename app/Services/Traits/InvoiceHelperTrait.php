<?php

namespace App\Services\Traits;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait InvoiceHelperTrait
{
    protected function createInvoice(array $data)
    {
        try {
            unset($data['invoice_number']);

            Log::info('[createInvoice] محاولة إنشاء الفاتورة...', $data);

            $invoice = Invoice::create([
                'invoice_type_id'   => $data['invoice_type_id'],
                'invoice_type_code' => $data['invoice_type_code'] ?? null,
                'due_date'          => $data['due_date'] ?? null,
                'status'            => $data['status'] ?? 'confirmed',
                'user_id'           => $data['user_id'],
                'gross_amount'      => $data['gross_amount'],
                'total_discount'    => $data['total_discount'] ?? 0,
                'net_amount'        => $data['net_amount'],
                'paid_amount'       => $data['paid_amount'] ?? 0,
                'remaining_amount'  => $data['remaining_amount'] ?? 0,
                'round_step'        => $data['round_step'] ?? null,
                'company_id'        => $data['company_id'] ?? null,
                'created_by'        => $data['created_by'] ?? null,
            ]);

            Log::info('[createInvoice] ✅ تم إنشاء الفاتورة بنجاح', ['invoice_id' => $invoice->id]);
            return $invoice;
        } catch (\Throwable $e) {
            Log::error('[createInvoice] ❌ فشل في إنشاء الفاتورة', [
                'exception' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    protected function createInvoiceItems($invoice, array $items, $companyId = null, $createdBy = null)
    {
        foreach ($items as $index => $item) {
            try {
                Log::info("[createInvoiceItems] محاولة إنشاء بند رقم $index", $item);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'] ?? null,
                    'name'       => $item['name'],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount'   => $item['discount'] ?? 0,
                    'total'      => $item['total'],
                    'company_id' => $companyId,
                    'created_by' => $createdBy,
                ]);

                Log::info("[createInvoiceItems] ✅ تم إنشاء البند رقم $index بنجاح");
            } catch (\Throwable $e) {
                Log::error("[createInvoiceItems] ❌ فشل إنشاء البند رقم $index", [
                    'exception' => $e->getMessage(),
                    'item' => $item
                ]);
                throw $e;
            }
        }
    }

    protected function updateInvoiceItems($invoice, array $items, $companyId = null, $createdBy = null)
    {
        try {
            $this->deleteInvoiceItems($invoice);
            $this->createInvoiceItems($invoice, $items, $companyId, $createdBy);
        } catch (\Throwable $e) {
            Log::error('[updateInvoiceItems] ❌ فشل في تحديث البنود', [
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function deleteInvoiceItems($invoice)
    {
        Log::info('[deleteInvoiceItems] حذف البنود القديمة للفاتورة', ['invoice_id' => $invoice->id]);
        InvoiceItem::where('invoice_id', $invoice->id)->delete();
    }
    protected function checkVariantsStock(array $items, string $mode = 'deduct')
    {
        try {
            if ($mode === 'none') return;

            foreach ($items as $index => $item) {
                $variantId = $item['variant_id'] ?? null;
                $variant = $variantId ? ProductVariant::find($variantId) : null;

                if (!$variant) {
                    throw ValidationException::withMessages([
                        "items.$index.variant_id" => ["المتغير المختار غير موجود (ID: $variantId)"],
                    ]);
                }

                $totalAvailableQuantity = $variant->stocks()->where('status', 'available')->sum('quantity');
                if ($mode === 'deduct' && $totalAvailableQuantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => ['الكمية غير متوفرة في المخزون.'],
                    ]);
                }
            }

            Log::info('[checkVariantsStock] ✅ تم التحقق من الكمية بنجاح');
        } catch (\Throwable $e) {
            Log::error('[checkVariantsStock] ❌ فشل في التحقق من المخزون', [
                'exception' => $e->getMessage(),
                'items' => $items,
            ]);
            throw $e;
        }
    }


    protected function deductStockForItems(array $items)
    {
        try {
            foreach ($items as $item) {
                $variant = ProductVariant::find($item['variant_id'] ?? null);
                if (!$variant) continue;

                $remaining = $item['quantity'];
                $stocks = $variant->stocks()->where('status', 'available')->orderBy('created_at', 'asc')->get();

                foreach ($stocks as $stock) {
                    if ($remaining <= 0) break;

                    $deduct = min($remaining, $stock->quantity);
                    if ($deduct > 0) {
                        $stock->decrement('quantity', $deduct);
                        Log::info('[deductStockForItems] خصم الكمية من المخزن', [
                            'variant_id' => $variant->id,
                            'stock_id'   => $stock->id,
                            'deducted'   => $deduct
                        ]);
                        $remaining -= $deduct;
                    }
                }
            }

            Log::info('[deductStockForItems] ✅ تم خصم الكمية بنجاح');
        } catch (\Throwable $e) {
            Log::error('[deductStockForItems] ❌ فشل خصم الكمية من المخزون', [
                'exception' => $e->getMessage(),
                'items' => $items,
            ]);
            throw $e;
        }
    }

    protected function returnStockForItems(Invoice $invoice)
    {
        try {
            foreach ($invoice->items as $item) {
                $variant = ProductVariant::find($item->variant_id ?? null);
                if (!$variant) continue;

                $remaining = $item->quantity;

                $stocks = $variant->stocks()->where('status', 'available')->orderBy('created_at', 'desc')->get();

                foreach ($stocks as $stock) {
                    if ($remaining <= 0) break;

                    $stock->increment('quantity', $remaining); // ترجيع كامل
                    Log::info('[returnStockForItems] ✅ تم ترجيع الكمية', [
                        'variant_id' => $variant->id,
                        'stock_id'   => $stock->id,
                        'returned'   => $remaining,
                    ]);

                    break; // بنرجع الكمية مرة واحدة للمخزن الأحدث
                }
            }

            Log::info('[returnStockForItems] ✅ تم إنهاء ترجيع الكميات بنجاح');
        } catch (\Throwable $e) {
            Log::error('[returnStockForItems] ❌ فشل أثناء ترجيع المخزون', [
                'exception' => $e->getMessage(),
                'invoice_id' => $invoice->id,
            ]);

            throw $e;
        }
    }
}

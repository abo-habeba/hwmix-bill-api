<?php

namespace App\Services\Traits;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait InvoiceHelperTrait
{
    /**
     * إنشاء فاتورة جديدة.
     */
    protected function createInvoice(array $data)
    {
        try {
            unset($data['invoice_number']);
            $invoice =  Invoice::create([
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
            return $invoice;
        } catch (\Throwable $e) {
            Log::error('فشل في انشاء الفاتوره بالفاتورة', [
                'exception' => $e->getMessage(),
            ]);
            return api_exception($e);
        }
    }

    /**
     * إنشاء بنود الفاتورة.
     */
    protected function createInvoiceItems($invoice, array $items, $companyId = null, $createdBy = null)
    {
        try {
            foreach ($items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'] ?? null,
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'total' => $item['total'],
                    'company_id' => $companyId,
                    'created_by' => $createdBy,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('فشل في انشاء عناصر بالفاتورة', [
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);
            return api_exception($e);
        }
    }

    /**
     * تحديث بنود الفاتورة (حذف القديم وإنشاء الجديد).
     */
    protected function updateInvoiceItems($invoice, array $items, $companyId = null, $createdBy = null)
    {
        try {
            $this->deleteInvoiceItems($invoice);
            $this->createInvoiceItems($invoice, $items, $companyId, $createdBy);
        } catch (\Throwable $e) {
            Log::error('فشل في تحديث عناصر  بالفاتورة', [
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);
            return api_exception($e);
        }
    }

    /**
     * حذف جميع بنود الفاتورة.
     */
    protected function deleteInvoiceItems($invoice)
    {
        InvoiceItem::where('invoice_id', $invoice->id)->delete();
    }

    /**
     * التحقق من توفر الكمية في المخزون للمتغيرات المطلوبة.
     *
     * @param array $items
     * @param string $mode 'deduct' (خصم) | 'add' (إضافة) | 'none' (تجاهل التحقق)
     * @throws ValidationException
     */
    protected function checkVariantsStock(array $items, string $mode = 'deduct')
    {

        try {
            if ($mode === 'none') return;
            foreach ($items as $item) {
                $variant = ProductVariant::find($item['variant_id'] ?? null);
                if (!$variant) {
                    throw ValidationException::withMessages([
                        'variant_id' => ['المتغير بمعرف ' . ($item['variant_id'] ?? '-') . ' غير موجود.'],
                    ]);
                }
                $totalAvailablequantity = $variant->stocks()->where('status', 'available')->sum('quantity');
                if ($mode === 'deduct' && $totalAvailablequantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'stock' => ['الكمية غير متوفرة في المخزون'],
                    ]);
                }
                // في حالة الإضافة للمخزون يمكن إضافة منطق تحقق إضافي لاحقًا
            }
        } catch (\Throwable $e) {
            Log::error('فشل في في التحقق من وجود المخزون الفاتورة', [
                'exception' => $e->getMessage(),
            ]);
            return api_exception($e);
        }
    }

    /**
     * خصم الكمية من المخزون لكل متغير بناءً على البنود.
     *
     * @param array $items
     */
    protected function deductStockForItems(array $items)
    {
        try {
            foreach ($items as $item) {
                $currentVariant = ProductVariant::find($item['variant_id'] ?? null);
                if (!$currentVariant) continue;
                $remainingQuantityToDeduct = $item['quantity'];
                $availableStocks = $currentVariant->stocks()->where('status', 'available')->orderBy('created_at', 'asc')->get();
                foreach ($availableStocks as $stock) {
                    if ($remainingQuantityToDeduct <= 0) break;
                    $deductquantity = min($remainingQuantityToDeduct, $stock->quantity);
                    if ($deductquantity > 0) {
                        $stock->decrement('quantity', $deductquantity);
                        $remainingQuantityToDeduct -= $deductquantity;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('فشل خصم المخزون مقابل العناصر بالفاتورة', [
                'exception' => $e->getMessage(),
            ]);
            return api_exception($e);
        }
    }
}

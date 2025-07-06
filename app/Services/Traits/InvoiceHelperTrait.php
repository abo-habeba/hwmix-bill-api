<?php

namespace App\Services\Traits;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

trait InvoiceHelperTrait
{
    /**
     * إنشاء فاتورة جديدة.
     */
    protected function createInvoice(array $data)
    {
        unset($data['invoice_number']);
        return Invoice::create([
            'invoice_type_id' => $data['invoice_type_id'],
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'confirmed',
            'user_id' => $data['user_id'],
            'total_amount' => $data['total_amount'],
            'paid_amount' => $data['paid_amount'],
            'total_discount' => $data['paid_amount'],
            'remaining_amount' => $data['remaining_amount'],
            'company_id' => $data['company_id'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * إنشاء بنود الفاتورة.
     */
    protected function createInvoiceItems($invoice, array $items, $companyId = null, $createdBy = null)
    {
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
    }

    /**
     * تحديث بنود الفاتورة (حذف القديم وإنشاء الجديد).
     */
    protected function updateInvoiceItems($invoice, array $items, $companyId = null, $createdBy = null)
    {
        $this->deleteInvoiceItems($invoice);
        $this->createInvoiceItems($invoice, $items, $companyId, $createdBy);
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
    }

    /**
     * خصم الكمية من المخزون لكل متغير بناءً على البنود.
     *
     * @param array $items
     */
    protected function deductStockForItems(array $items)
    {
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
    }
}

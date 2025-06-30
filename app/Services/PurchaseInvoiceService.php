<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;

class PurchaseInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    public function create(array $data)
    {
        // التحقق من المنتجات
        foreach ($data['items'] as $item) {
            $variant = ProductVariant::find($item['variant_id']);
            if (!$variant) {
                throw ValidationException::withMessages([
                    'variant_id' => ['المتغير بمعرف ' . $item['variant_id'] . ' غير موجود.'],
                ]);
            }
        }
        $invoice = $this->createInvoice($data);
        $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
        // زيادة الكمية في المخزون
        foreach ($data['items'] as $item) {
            $currentVariant = ProductVariant::find($item['variant_id']);
            $stock = $currentVariant->stocks()->where('status', 'available')->orderBy('created_at', 'desc')->first();
            if ($stock) {
                $stock->increment('quantity', $item['quantity']);
            } else {
                \App\Models\Stock::create([
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'status' => 'available',
                    'company_id' => $data['company_id'] ?? null,
                    'created_by' => $data['created_by'] ?? null,
                ]);
            }
        }
        $invoice->logCreated('إنشاء فاتورة شراء رقم ' . $invoice->invoice_number);
        $cashBoxId = $data['cash_box_id'] ?? null;
        // خصم المدفوع من رصيد المستخدم الحالي
        $authUser = Auth::user();
        $authUser->withdraw($invoice->paid_amount, $cashBoxId);
        // إضافة المتبقي لرصيد المورد (user_id) إذا كان مختلفاً عن المستخدم الحالي
        if ($invoice->user_id && $invoice->user_id != $authUser->id && $invoice->remaining_amount > 0) {
            $supplier = \App\Models\User::find($invoice->user_id);
            if ($supplier) {
                $supplier->deposit($invoice->remaining_amount, $cashBoxId);
            }
        }
        return $invoice;
    }
}

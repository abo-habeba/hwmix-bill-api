<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;

class SaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    public function create(array $data)
    {
        $this->checkVariantsStock($data['items']);
        $invoice = $this->createInvoice($data);
        $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
        // خصم الكمية من المخزون
        $this->deductStockForItems($data['items']);
        $invoice->logCreated('إنشاء فاتورة بيع رقم ' . $invoice->invoice_number);
        $authUser = Auth::user();
        $cashBoxId = $data['cash_box_id'] ?? null;
        // إضافة المدفوع فقط لرصيد المستخدم الحالي
        $authUser->deposit($invoice->paid_amount, $cashBoxId);
        // خصم المتبقي من رصيد المشتري (user_id)
        if ($invoice->user_id && $invoice->user_id != $authUser->id && $invoice->remaining_amount > 0) {
            $buyer = \App\Models\User::find($invoice->user_id);
            if ($buyer) {
                $buyer->withdraw($invoice->remaining_amount, $cashBoxId);
            }
        }
        return $invoice;
    }
}

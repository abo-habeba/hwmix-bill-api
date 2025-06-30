<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;

class ServiceInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    public function create(array $data)
    {
        $invoice = $this->createInvoice($data);
        $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
        $invoice->logCreated('إنشاء فاتورة خدمة رقم ' . $invoice->invoice_number);
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

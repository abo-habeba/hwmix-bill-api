<?php

namespace App\Services;

use App\Models\User;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use App\Services\UserSelfPurchaseHandler;
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
        $authUser  = Auth::user();
        $cashBoxId = $data['cash_box_id'] ?? null;
        // إضافة المدفوع فقط لرصيد المستخدم الحالي
        // إذا كان المشتري هو نفسه الموظف
        if ($invoice->user_id && $invoice->user_id == $authUser->id) {
            app(UserSelfDebtService::class)
                ->registerPurchase($authUser, $invoice->paid_amount, $invoice->remaining_amount, $cashBoxId, $invoice->company_id);
        } else if ($invoice->user_id && $invoice->user_id != $authUser->id && $invoice->remaining_amount > 0) {
            $buyer = User::find($invoice->user_id);
            if ($buyer) {
                $authUser->deposit($invoice->paid_amount, $cashBoxId);
                $buyer->withdraw($invoice->remaining_amount, $cashBoxId);
            }
        }
        return $invoice;
    }
}

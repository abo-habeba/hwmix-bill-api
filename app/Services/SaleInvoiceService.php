<?php

namespace App\Services;

use App\Models\User;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\UserSelfPurchaseHandler;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;

class SaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    public function create(array $data)
    {
        try {
            $this->checkVariantsStock($data['items']);
            \Log::info('بيانات الفاتورة المرسلة:', $data);
            \Log::info('البنود:', $data['items'] ?? []);

            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                Log::error('فشل إنشاء الفاتورة');
                throw new \Exception('فشل إنشاء الفاتورة');
            }

            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            $this->deductStockForItems($data['items']);

            Log::info('تم إنشاء الفاتورة', [
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'gross' => $invoice->gross_amount,
                'paid' => $invoice->paid_amount,
                'remaining' => $invoice->remaining_amount,
            ]);

            $invoice->logCreated('إنشاء فاتورة بيع رقم ' . $invoice->invoice_number);

            $authUser  = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;


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
        } catch (\Throwable $e) {
            Log::error('فشل في عمليات الرصيد الخاصة بالفاتورة', [
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);
            return api_exception($e);
        }

        return $invoice;
    }
}

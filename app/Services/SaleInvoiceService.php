<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\Traits\InvoiceHelperTrait;
use App\Services\DocumentServiceInterface;

class SaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    public function create(array $data)
    {
        try {
            Log::info('[SaleInvoice] 📥 البيانات المستلمة:', $data);

            $this->checkVariantsStock($data['items']);
            Log::info('[checkVariantsStock] ✅ تم التحقق من الكمية بنجاح');

            Log::info('[createInvoice] محاولة إنشاء الفاتورة...', $data);
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                Log::error('[createInvoice] ❌ فشل إنشاء الفاتورة');
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }
            Log::info('[createInvoice] ✅ تم إنشاء الفاتورة بنجاح', ['invoice_id' => $invoice->id]);

            foreach ($data['items'] as $index => $item) {
                Log::info("[createInvoiceItems] محاولة إنشاء بند رقم $index", $item);
            }

            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
            Log::info('[createInvoiceItems] ✅ تم إنشاء كل البنود بنجاح');

            $this->deductStockForItems($data['items']);
            Log::info('[deductStockForItems] ✅ تم خصم الكميات من المخزون بنجاح');

            $authUser  = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;

            // معالجة الرصيد
            if ($invoice->user_id && $invoice->user_id == $authUser->id) {
                Log::info('[رصيد] 🧾 المستخدم بيشتري لنفسه');
                app(UserSelfDebtService::class)->registerPurchase(
                    $authUser,
                    $invoice->paid_amount,
                    $invoice->remaining_amount,
                    $cashBoxId,
                    $invoice->company_id
                );
                Log::info('[رصيد] ✅ تم تسجيل الشراء الذاتي بنجاح');
            } elseif ($invoice->user_id && $invoice->user_id != $authUser->id && $invoice->remaining_amount > 0) {
                Log::info('[رصيد] 🧾 محاولة خصم من العميل وإيداع للموظف');
                $buyer = User::find($invoice->user_id);
                if ($buyer) {
                    $authUser->deposit($invoice->paid_amount, $cashBoxId);
                    $buyer->withdraw($invoice->remaining_amount, $cashBoxId);
                    Log::info('[رصيد] ✅ تم إتمام العمليات المالية');
                } else {
                    Log::warning('[رصيد] ⚠️ لم يتم العثور على العميل');
                }
            }

            Log::info('[SaleInvoice] ✅ تم إنشاء الفاتورة بنجاح', [
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'gross' => $invoice->gross_amount,
                'paid' => $invoice->paid_amount,
                'remaining' => $invoice->remaining_amount,
            ]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('[SaleInvoice] 💥 استثناء أثناء إنشاء فاتورة البيع', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // ليتم التعامل معه في دالة store باستخدام api_exception
        }
    }
}

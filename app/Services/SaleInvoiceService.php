<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use Exception;

class SaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            // 1. التحقق من المخزون
            $this->checkVariantsStock($data['items']);
            Log::info('[SaleInvoice] 📥 البيانات المستلمة:', $data);

            // 2. إنشاء الفاتورة
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                Log::error('[SaleInvoice] ❌ فشل إنشاء الفاتورة');
                throw new Exception('فشل إنشاء الفاتورة.');
            }

            // 3. إنشاء البنود
            try {
                $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
            } catch (Exception $e) {
                Log::error('[SaleInvoice] ❌ فشل إنشاء بنود الفاتورة', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
                throw new Exception('فشل إنشاء البنود.');
            }

            // 4. خصم من المخزون
            try {
                $this->deductStockForItems($data['items']);
            } catch (Exception $e) {
                Log::error('[SaleInvoice] ❌ فشل خصم المخزون', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
                throw new Exception('فشل خصم المخزون.');
            }

            // 5. تسجيل الفاتورة في اللوج
            try {
                $invoice->logCreated('إنشاء فاتورة بيع رقم ' . $invoice->invoice_number);
            } catch (Exception $e) {
                Log::warning('[SaleInvoice] ⚠️ فشل تسجيل لوج إنشاء الفاتورة', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
                // لا نوقف التنفيذ، فقط نسجل التحذير
            }

            Log::info('[SaleInvoice] ✅ تم إنشاء الفاتورة بنجاح', [
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'gross' => $invoice->gross_amount,
                'paid' => $invoice->paid_amount,
                'remaining' => $invoice->remaining_amount,
            ]);

            // 6. التعامل مع أرصدة المستخدمين
            try {
                $authUser  = Auth::user();
                $cashBoxId = $data['cash_box_id'] ?? null;

                if ($invoice->user_id && $invoice->user_id != $authUser->id && $invoice->remaining_amount > 0) {
                    $buyer = User::find($invoice->user_id);

                    if ($buyer) {
                        $authUser->deposit($invoice->paid_amount, $cashBoxId);
                        $buyer->withdraw($invoice->remaining_amount, $cashBoxId);
                    } else {
                        Log::warning('[SaleInvoice] ⚠️ لم يتم العثور على المستخدم المشتري', ['user_id' => $invoice->user_id]);
                    }
                }
            } catch (Exception $e) {
                Log::error('[SaleInvoice] ❌ فشل في معالجة الأرصدة', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
                throw new Exception('حدث خطأ أثناء تحديث أرصدة المستخدمين.');
            }

            DB::commit();
            return $invoice;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('[SaleInvoice] ❌ فشل في إنشاء فاتورة البيع بالكامل', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return api_exception($e);
        }
    }
}

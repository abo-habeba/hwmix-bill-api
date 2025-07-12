<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;

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

    public function update(array $data, Invoice $invoice)
    {
        try {
            Log::info('[SaleInvoice:Update] 🚧 بدء تعديل الفاتورة رقم ' . $invoice->id);

            // 1️⃣ التحقق من الحالة
            if ($invoice->status === 'paid') {
                throw new \Exception('لا يمكن تعديل فاتورة تم سدادها بالكامل.');
            }

            // 2️⃣ التحقق من الكمية الجديدة
            $this->checkVariantsStock($data['items']);

            // 3️⃣ استرجاع المخزون السابق
            Log::info('[SaleInvoice:Update] ⏪ محاولة استرجاع الكمية القديمة من المخزون');
            $this->returnStockForItems($invoice); // هنعملها تحت

            // 4️⃣ حذف البنود القديمة
            $this->deleteInvoiceItems($invoice);

            // 5️⃣ تحديث بيانات الفاتورة
            $invoice->update([
                'due_date'         => $data['due_date'] ?? $invoice->due_date,
                'user_id'          => $data['user_id'],
                'gross_amount'     => $data['gross_amount'],
                'total_discount'   => $data['total_discount'] ?? 0,
                'net_amount'       => $data['net_amount'],
                'paid_amount'      => $data['paid_amount'] ?? 0,
                'remaining_amount' => $data['remaining_amount'] ?? 0,
                'round_step'       => $data['round_step'] ?? null,
            ]);

            // 6️⃣ إضافة البنود الجديدة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            // 7️⃣ خصم الكميات من المخزون
            $this->deductStockForItems($data['items']);

            // 8️⃣ إعادة التعامل مع الرصيد لو لزم
            $authUser  = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;

            if ($invoice->user_id && $invoice->user_id == $authUser->id) {
                app(UserSelfDebtService::class)->registerPurchase(
                    $authUser,
                    $invoice->paid_amount,
                    $invoice->remaining_amount,
                    $cashBoxId,
                    $invoice->company_id
                );
            } elseif ($invoice->user_id && $invoice->user_id != $authUser->id && $invoice->remaining_amount > 0) {
                $buyer = User::find($invoice->user_id);
                if ($buyer) {
                    $authUser->deposit($invoice->paid_amount, $cashBoxId);
                    $buyer->withdraw($invoice->remaining_amount, $cashBoxId);
                }
            }

            Log::info('[SaleInvoice:Update] ✅ تم تعديل الفاتورة بنجاح', ['invoice_id' => $invoice->id]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('[SaleInvoice:Update] 💥 خطأ أثناء تعديل الفاتورة', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            Log::info('[SaleInvoice:Cancel] 🚫 محاولة إلغاء الفاتورة رقم ' . $invoice->id);

            // 1️⃣ تحقق من إمكانية الإلغاء
            if ($invoice->status === 'paid') {
                throw new \Exception('لا يمكن إلغاء فاتورة مدفوعة بالكامل.');
            }

            // 2️⃣ استرجاع الكمية للمخزون
            $this->returnStockForItems($invoice);

            // 3️⃣ تغيير حالة الفاتورة
            $invoice->update([
                'status' => 'canceled',
            ]);

            // 4️⃣ حذف البنود (اختياري حسب رؤيتك)
            $this->deleteInvoiceItems($invoice);

            Log::info('[SaleInvoice:Cancel] ✅ تم إلغاء الفاتورة بنجاح', ['invoice_id' => $invoice->id]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('[SaleInvoice:Cancel] ❌ فشل أثناء إلغاء الفاتورة', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

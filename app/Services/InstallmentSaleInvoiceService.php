<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;

class InstallmentSaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;
public function create(array $data)
{
    try {
        \Log::info('[InstallmentInvoice] 📥 البيانات المستلمة:', $data);

        $this->checkVariantsStock($data['items']);
        \Log::info('[InstallmentInvoice] ✅ تحقق من الكمية بنجاح');

        $invoice = $this->createInvoice($data);
        $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
        \Log::info('[InstallmentInvoice] ✅ إنشاء البنود بنجاح');

        $this->deductStockForItems($data['items']);
        \Log::info('[InstallmentInvoice] ✅ خصم الكمية من المخزون بنجاح');

        // ⬅️ خصم المقدم من العميل
        if (!empty($data['paid_amount']) && $data['paid_amount'] > 0) {
            \Log::info('[InstallmentInvoice] 💸 خصم المبلغ المدفوع من العميل رقم ' . $data['user_id']);
            $user = \App\Models\User::findOrFail($data['user_id']);
            $withdrawResult = $user->withdraw($data['paid_amount'], $data['cash_box_id'] ?? null);

            if ($withdrawResult !== true) {
                throw new \Exception('فشل خصم المبلغ من العميل: ' . json_encode($withdrawResult));
            }

            // إيداع في خزنة الموظف الحالي (لو فيه logged-in)
            $staff = \App\Models\User::find($data['created_by'] ?? Auth::id());
            if ($staff) {
                \Log::info('[InstallmentInvoice] 💰 إيداع المبلغ في خزنة الموظف رقم ' . $staff->id);
                $depositResult = $staff->deposit($data['paid_amount'], $data['cash_box_id'] ?? null);

                if ($depositResult !== true) {
                    throw new \Exception('فشل إيداع المبلغ في خزنة الموظف: ' . json_encode($depositResult));
                }
            }
        }

        // ⬅️ إنشاء خطة الأقساط
        if (isset($data['installment_plan'])) {
            \Log::info('[InstallmentInvoice] 📅 إنشاء خطة التقسيط');
            $installmentService = new \App\Services\InstallmentService();
            $installmentService->createInstallments($data, $invoice->id);
        }

        $invoice->logCreated('إنشاء فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);
        \Log::info('[InstallmentInvoice] ✅ إنشاء الفاتورة بالتقسيط بنجاح', ['invoice_id' => $invoice->id]);

        return $invoice;
    } catch (\Throwable $e) {
        \Log::error('[InstallmentInvoice] 💥 استثناء أثناء إنشاء الفاتورة:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        throw $e;
    }
}
}

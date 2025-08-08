<?php

namespace App\Services\Invoice; // تم تعديل namespace

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use App\Services\Invoice\InstallmentService;
use App\Services\Financial\FinancialServiceInterface; // استيراد الواجهة المالية
use App\Services\UserSelfDebtService; // سنبقيها للاستخدام المستقبلي غير المالي
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // إضافة DB facade

class InstallmentSaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    protected FinancialServiceInterface $financialService;
    protected UserSelfDebtService $userSelfDebtService; // سنبقيها للاستخدام المستقبلي غير المالي

    public function __construct(FinancialServiceInterface $financialService, UserSelfDebtService $userSelfDebtService)
    {
        $this->financialService = $financialService;
        $this->userSelfDebtService = $userSelfDebtService; // حقن UserSelfDebtService
    }

    /**
     * إنشاء فاتورة بيع بالتقسيط جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            Log::info('InstallmentSaleInvoiceService: بدء إنشاء فاتورة بيع بالتقسيط.', ['data' => $data]);

            // 1. التحقق من توافر المنتجات في المخزون
            $this->checkVariantsStock($data['items']);

            // 2. حساب البيانات المالية للفاتورة (الربح التقديري، الحالة، المتبقي) باستخدام الخدمة المالية
            // ملاحظة: InstallmentSale هي نوع من Sale، لذا تستخدم نفس منطق حساب الأرباح والحالة
            $data = $this->financialService->calculateInvoiceFinancials($data);

            // 3. إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            // 4. إنشاء بنود الفاتورة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'], $data['created_by']);

            // 5. خصم الكمية من المخزون
            $this->deductStockForItems($data['items']);

            // 6. إنشاء المعاملات المالية للفاتورة (قيود الاستحقاق الخاصة بالبيع بالتقسيط)
            // هذه الطريقة ستحتاج إلى تعريف في FinancialServiceInterface و FinancialService
            $this->financialService->createInstallmentSaleFinancialTransactions(
                $invoice,
                $data,
                $data['company_id'],
                $data['user_id'],
                $data['created_by']
            );

            // 7. معالجة الدفعة الأولى إذا كانت موجودة (دفعة واردة)
            $downPayment = $data['installment_plan']['down_payment'] ?? 0;
            $cashBoxId = $data['cash_box_id'] ?? null;
            if ($downPayment > 0 && $cashBoxId) {
                $this->financialService->handleInvoicePayment(
                    $invoice,
                    $downPayment,
                    $cashBoxId,
                    $data['company_id'],
                    $data['user_id'],
                    $data['created_by']
                );
            }

            // 8. إنشاء خطة الأقساط
            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            // 9. استدعاء UserSelfDebtService للمنطق غير المالي الخاص بالموظف كعميل
            // ملاحظة: تم إزالة المنطق المالي المباشر من UserSelfDebtService
            // إذا كان buyer هو نفسه AuthUser، يمكن استدعاء الدالة هنا لأي منطق غير مالي
            $authUser = Auth::user();
            $buyer = User::find($data['user_id']);
            if ($buyer && $authUser && $buyer->id == $authUser->id) {
                // لا يتم تمرير cashBoxId و userCashBoxId لأن المنطق المالي ليس هنا
                $this->userSelfDebtService->handleSelfSaleDebt($authUser, $invoice, $downPayment, $data['installment_plan']['total_amount'] ?? 0);
            }


            // تسجيل عملية الإنشاء
            $invoice->logCreated('إنشاء فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في إنشاء فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * تحديث فاتورة بيع بالتقسيط موجودة.
     *
     * @param array $data البيانات الجديدة للفاتورة.
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('InstallmentSaleInvoiceService: بدء تحديث فاتورة بيع بالتقسيط.', ['invoice_id' => $invoice->id, 'data' => $data]);

            // 1. إلغاء الفاتورة القديمة أولاً (يعكس جميع التأثيرات المالية والمخزنية)
            // ملاحظة: دالة cancel ستحدث حالة الفاتورة القديمة إلى 'canceled'
            $this->cancel($invoice);

            // 2. إعادة إنشاء فاتورة جديدة بالبيانات المحدثة
            $newInvoice = $this->create($data);

            // تسجيل عملية التحديث للفاتورة الجديدة
            $newInvoice->logUpdated('تحديث فاتورة بيع بالتقسيط رقم ' . $newInvoice->invoice_number . ' (تم استبدال الفاتورة القديمة ' . $invoice->invoice_number . ')');

            return $newInvoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في تحديث فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إلغاء فاتورة بيع بالتقسيط.
     *
     * @param Invoice $invoice الفاتورة المراد إلغاؤها.
     * @return Invoice الفاتورة الملغاة.
     * @throws \Exception إذا كانت الفاتورة ملغاة بالفعل.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            Log::info('InstallmentSaleInvoiceService: بدء إلغاء فاتورة بيع بالتقسيط.', ['invoice_id' => $invoice->id]);

            if ($invoice->status === 'canceled') {
                throw new \Exception('لا يمكن إلغاء فاتورة ملغاة بالفعل.');
            }

            // 1. عكس تأثير الأقساط المرتبطة (إذا كانت موجودة)
            // هذه الدالة تقوم فقط بتحديث حالة الأقساط وخطة الأقساط إلى "ملغاة".
            // لا تقوم بمعالجة الدفعات المالية أو عكسها بشكل مباشر.
            if ($invoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($invoice);
            } else {
                Log::warning('InstallmentSaleInvoiceService: لا توجد خطة أقساط مرتبطة بالفاتورة للإلغاء.', ['invoice_id' => $invoice->id]);
            }

            // 2. عكس تأثير المخزون (إعادة المنتجات إلى المخزون)
            $this->returnStockForItems($invoice);

            // 3. عكس/حذف الدفعات والمعاملات المالية المرتبطة بها باستخدام الخدمة المالية
            // هذا سيعكس الدفعة الأولى وأي دفعات أقساط تم تسجيلها كـ Payment
            $this->financialService->reverseInvoicePayments($invoice);

            // 4. عكس/حذف المعاملات المالية المرتبطة بالفاتورة نفسها (قيود الاستحقاق)
            // هذه الطريقة ستحتاج إلى تعريف في FinancialServiceInterface و FinancialService
            $this->financialService->reverseInstallmentSaleFinancialTransactions($invoice);

            // 5. استدعاء UserSelfDebtService للمنطق غير المالي الخاص بالموظف كعميل عند الإلغاء
            $authUser = Auth::user();
            $buyer = User::find($invoice->user_id);
            if ($buyer && $authUser && $buyer->id == $authUser->id) {
                // لا يتم تمرير cashBoxId و userCashBoxId لأن المنطق المالي ليس هنا
                $this->userSelfDebtService->clearSelfSaleDebt($authUser, $invoice);
            }

            // 6. تحديث حالة الفاتورة إلى 'canceled' والقيام بـ soft delete لبنودها
            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice); // يقوم بـ soft delete لبنود الفاتورة

            // تسجيل عملية الإلغاء
            $invoice->logCanceled('إلغاء فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في إلغاء فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

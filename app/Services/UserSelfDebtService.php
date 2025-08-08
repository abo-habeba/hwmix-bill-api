<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice; // يجب استيراد نموذج الفاتورة
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\Financial\FinancialServiceInterface; // استيراد الواجهة المالية

class UserSelfDebtService
{
    // لم نعد نحتاج لحقن FinancialServiceInterface هنا إذا كانت هذه الخدمة لن تقوم بإجراء عمليات مالية مباشرة
    // ولكن يمكن الاحتفاظ بها إذا كانت ستستدعي FinancialService لأغراض غير مرتبطة بالفواتير مباشرة في المستقبل.
    // protected FinancialServiceInterface $financialService;

    // public function __construct(FinancialServiceInterface $financialService)
    // {
    //     $this->financialService = $financialService;
    // }

    /**
     * معالجة دين البيع للموظف لنفسه عند إنشاء فاتورة بيع بالتقسيط.
     *
     * ملاحظة هامة: المنطق المالي (مثل تأثير الدفعة الأولى والدين المتبقي على الأرصدة والمعاملات المالية)
     * يتم التعامل معه الآن بالكامل بواسطة SaleInvoiceService الذي يستدعي FinancialService.
     * هذه الدالة الآن هي مكان مخصص لأي منطق عمل إضافي خاص بحالات "الموظف كعميل"
     * لا يندرج ضمن المعاملات المالية الأساسية للفاتورة.
     *
     * @param User $user المستخدم الموظف.
     * @param Invoice $invoice الفاتورة المرتبطة.
     * @param float $downPayment الدفعة الأولى.
     * @param float $totalInstallmentAmount إجمالي مبلغ الأقساط.
     * @param int|null $companyCashBoxId معرف صندوق النقدية للشركة.
     * @param int|null $userCashBoxId معرف صندوق النقدية للمستخدم (الموظف كعميل).
     * @return void
     * @throws \Throwable
     */
    public function handleSelfSaleDebt(User $user, Invoice $invoice, float $downPayment, float $totalInstallmentAmount, ?int $companyCashBoxId = null, ?int $userCashBoxId = null): void
    {
        Log::info('UserSelfDebtService: الدالة handleSelfSaleDebt تم استدعاؤها. المنطق المالي يتم التعامل معه في SaleInvoiceService و FinancialService.');
        Log::info('UserSelfDebtService: هذا المكان مخصص لأي منطق خاص إضافي لبيع الموظف لنفسه.');
        try {
            // لا يوجد منطق مالي مباشر هنا.
            // أي تأثير على الأرصدة أو إنشاء معاملات مالية يجب أن يتم عبر FinancialService
            // من خلال خدمة الفاتورة (SaleInvoiceService).

            // مثال: إذا كان هناك منطق خاص غير مالي هنا (مثل إرسال إشعار خاص للموظف)
            // send_special_notification_to_employee($user, $invoice);

        } catch (\Throwable $e) {
            Log::error('UserSelfDebtService: فشل في معالجة دين البيع للموظف لنفسه (منطق غير مالي).', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'user_id' => $user->id]);
            throw $e;
        }
    }

    /**
     * مسح دين البيع للموظف لنفسه عند إلغاء فاتورة بيع بالتقسيط.
     *
     * ملاحظة هامة: المنطق المالي لعكس الدفعات والدين المتبقي
     * يتم التعامل معه الآن بالكامل بواسطة SaleInvoiceService الذي يستدعي FinancialService.
     * هذه الدالة الآن هي مكان مخصص لأي منطق عمل إضافي خاص بحالات "الموظف كعميل"
     * لا يندرج ضمن المعاملات المالية الأساسية للفاتورة.
     *
     * @param User $user المستخدم الموظف.
     * @param Invoice $invoice الفاتورة الملغاة.
     * @param int|null $companyCashBoxId معرف صندوق النقدية للشركة.
     * @param int|null $userCashBoxId معرف صندوق النقدية للمستخدم (الموظف كعميل).
     * @return void
     * @throws \Throwable
     */
    public function clearSelfSaleDebt(User $user, Invoice $invoice, ?int $companyCashBoxId = null, ?int $userCashBoxId = null): void
    {
        Log::info('UserSelfDebtService: الدالة clearSelfSaleDebt تم استدعاؤها. المنطق المالي يتم التعامل معه في SaleInvoiceService و FinancialService.');
        Log::info('UserSelfDebtService: هذا المكان مخصص لأي منطق خاص إضافي لإلغاء بيع الموظف لنفسه.');
        try {
            // لا يوجد منطق مالي مباشر هنا.
            // أي تأثير على الأرصدة أو عكس معاملات مالية يجب أن يتم عبر FinancialService
            // من خلال خدمة الفاتورة (SaleInvoiceService).

            // مثال: إذا كان هناك منطق خاص غير مالي هنا (مثل تحديث حالة خاصة للموظف)
            // update_employee_special_status($user, 'no_self_debt');

        } catch (\Throwable $e) {
            Log::error('UserSelfDebtService: فشل في مسح دين البيع للموظف لنفسه (منطق غير مالي).', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'user_id' => $user->id]);
            throw $e;
        }
    }

    /**
     * إنشاء سجل معاملة.
     *
     * ملاحظة هامة: هذه الدالة كانت تقوم بإنشاء سجلات في جدول 'transactions' وتتفاعل مباشرة مع أرصدة المستخدمين.
     * مع وجود FinancialService، يجب أن يتم التعامل مع جميع المعاملات المالية عبر
     * FinancialTransaction و Payment Models.
     * هذه الدالة لم تعد مطلوبة ويجب حذفها أو إعادة تسميتها بالكامل إذا كان لها استخدام آخر غير مالي.
     *
     * @param User $user المستخدم المعني.
     * @param string $type نوع المعاملة.
     * @param float $amount المبلغ.
     * @param string $description الوصف.
     * @param int|null $cashBoxId معرف صندوق النقدية.
     * @param int|null $companyId معرف الشركة.
     * @param int|null $invoiceId معرف الفاتورة المرتبطة.
     * @param string $transactionType نوع حركة الرصيد (deposit/withdrawal).
     * @return void
     * @throws \Throwable
     */
    protected function createTransaction(User $user, string $type, float $amount, string $description, ?int $cashBoxId = null, ?int $companyId = null, ?int $invoiceId = null, string $transactionType = 'deposit'): void
    {
        Log::warning('UserSelfDebtService: الدالة createTransaction تم استدعاؤها. هذه الدالة لم تعد مطلوبة ويجب حذفها. استخدم FinancialService بدلاً من ذلك.');
        // تم إزالة جميع الأكواد الداخلية لهذه الدالة.
        // يجب حذف هذه الدالة من الكلاس بالكامل عند الانتهاء من Refactoring.
    }
}

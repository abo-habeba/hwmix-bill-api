<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Invoice; // يجب استيراد نموذج الفاتورة
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UserSelfDebtService
{
    /**
     * معالجة دين البيع للموظف لنفسه عند إنشاء فاتورة بيع بالتقسيط.
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
        try {
            $companyId = $invoice->company_id ?? $user->company_id;

            // خصم الدفعة الأولى من رصيد الموظف كعميل (لأن هذا يمثل دفعًا منه)
            if ($downPayment > 0) {
                // $user->withdraw($downPayment, $userCashBoxId);
                $this->createTransaction(
                    user: $user,
                    type: 'خصم دفعة أولى (شراء لنفسه)',
                    amount: $downPayment,
                    description: 'خصم دفعة أولى من رصيد المستخدم (شراء لنفسه)',
                    cashBoxId: $userCashBoxId,
                    companyId: $companyId,
                    invoiceId: $invoice->id,
                    transactionType: 'withdrawal'
                );
            }

            // تسجيل دين التقسيط المتبقي على الموظف كعميل
            $installmentDebt = $totalInstallmentAmount - $downPayment;
            if ($installmentDebt > 0) {
                // $user->withdraw($installmentDebt, $userCashBoxId); // الموظف يصبح مديوناً
                $this->createTransaction(
                    user: $user,
                    type: 'تسجيل دين تقسيط (شراء لنفسه)',
                    amount: $installmentDebt,
                    description: 'تسجيل مديونية تقسيط على المستخدم (شراء لنفسه)',
                    cashBoxId: $userCashBoxId,
                    companyId: $companyId,
                    invoiceId: $invoice->id,
                    transactionType: 'withdrawal'
                );
            }
        } catch (\Throwable $e) {
            Log::error('UserSelfDebtService: فشل في معالجة دين البيع للموظف لنفسه.', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'user_id' => $user->id]);
            throw $e;
        }
    }

    /**
     * مسح دين البيع للموظف لنفسه عند إلغاء فاتورة بيع بالتقسيط.
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
        try {
            $companyId = $invoice->company_id ?? $user->company_id;

            // عكس الدفعة الأولى (إيداع المبلغ في رصيد الموظف كعميل)
            $initialDownPayment = $invoice->installmentPlan->down_payment ?? 0;
            if ($initialDownPayment > 0) {
                // $user->deposit($initialDownPayment, $userCashBoxId);
                $this->createTransaction(
                    user: $user,
                    type: 'عكس دفعة أولى (إلغاء شراء لنفسه)',
                    amount: $initialDownPayment,
                    description: 'إلغاء دفعة أولى وإرجاع المبلغ لرصيد المستخدم (إلغاء فاتورة)',
                    cashBoxId: $userCashBoxId,
                    companyId: $companyId,
                    invoiceId: $invoice->id,
                    transactionType: 'deposit'
                );
            }

            // عكس الدين المتبقي (إيداع المبلغ في رصيد الموظف كعميل)
            $totalInstallmentDebt = ($invoice->installmentPlan->total_amount ?? 0) - ($invoice->installmentPlan->down_payment ?? 0);
            if ($totalInstallmentDebt > 0) {
                // $user->deposit($totalInstallmentDebt, $userCashBoxId);
                $this->createTransaction(
                    user: $user,
                    type: 'عكس دين تقسيط (إلغاء شراء لنفسه)',
                    amount: $totalInstallmentDebt,
                    description: 'إلغاء مديونية تقسيط على المستخدم (إلغاء فاتورة)',
                    cashBoxId: $userCashBoxId,
                    companyId: $companyId,
                    invoiceId: $invoice->id,
                    transactionType: 'deposit'
                );
            }
        } catch (\Throwable $e) {
            Log::error('UserSelfDebtService: فشل في مسح دين البيع للموظف لنفسه.', ['error' => $e->getMessage(), 'invoice_id' => $invoice->id, 'user_id' => $user->id]);
            throw $e;
        }
    }

    /**
     * إنشاء سجل معاملة.
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
        try {
            $balanceBefore = $user->balanceBox($cashBoxId);
            $balanceAfter  = ($transactionType === 'deposit') ? ($balanceBefore + $amount) : ($balanceBefore - $amount);

            Transaction::create([
                'user_id'        => $user->id,
                'type'           => $type,
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => $description,
                'cashbox_id'     => $cashBoxId,
                'company_id'     => $companyId,
                'created_by'     => Auth::id(),
                'invoice_id'     => $invoiceId,
            ]);
        } catch (\Throwable $e) {
            Log::error('UserSelfDebtService: فشل إنشاء المعاملة.', ['exception' => $e->getMessage(), 'user_id' => $user->id, 'type' => $type]);
            throw $e;
        }
    }
}

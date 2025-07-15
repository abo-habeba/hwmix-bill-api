<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction; // تم التأكد من استيراد نموذج المعاملات
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // تم التأكد من استيراد واجهة Auth

class UserSelfDebtService
{
    /**
     * تسجيل عملية شراء من قبل الموظف لنفسه.
     *
     * @param User $user المستخدم المعني.
     * @param float $paidAmount المبلغ المدفوع.
     * @param float $remainingAmount المبلغ المتبقي (الدين).
     * @param int|null $cashBoxId معرف صندوق النقدية.
     * @param int|null $companyId معرف الشركة.
     * @return void
     */
    public function registerPurchase(User $user, float $paidAmount, float $remainingAmount, ?int $cashBoxId = null, ?int $companyId = null): void
    {
        try {
            $companyId = $companyId ?? $user->company_id;

            if ($paidAmount > 0) {
                $user->deposit($paidAmount, $cashBoxId);
                $this->createTransaction(
                    user: $user,
                    type: 'إيداع',
                    amount: $paidAmount,
                    description: 'دفع نقدي عند شراء المستخدم لنفسه',
                    cashBoxId: $cashBoxId,
                    companyId: $companyId
                );
            }

            if ($remainingAmount > 0) {
                // هنا يتم تسجيل المديونية كمعاملة سحب من رصيد المستخدم (دين عليه)
                // لا يتم استخدام $user->withdraw هنا بشكل مباشر لأنه دين وليس سحب فعلي من الخزنة
                $this->createTransaction(
                    user: $user,
                    type: 'مديونية',
                    amount: $remainingAmount,
                    description: 'تسجيل مديونية بسبب شراء المستخدم لنفسه',
                    cashBoxId: $cashBoxId,
                    companyId: $companyId
                );
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * تسجيل عملية سداد مديونية أو دفع من المستخدم.
     *
     * @param User $user المستخدم المعني.
     * @param float $paidAmount المبلغ المدفوع (لسداد الدين).
     * @param float $remainingDebt المبلغ المتبقي من الدين بعد هذا الدفع (عادة 0 إذا تم السداد بالكامل).
     * @param int|null $cashBoxId معرف صندوق النقدية.
     * @param int|null $companyId معرف الشركة.
     * @return bool
     */
    public function registerPayment(User $user, float $paidAmount, float $remainingDebt, ?int $cashBoxId = null, ?int $companyId = null): bool
    {
        try {
            $companyId = $companyId ?? $user->company_id;

            if ($paidAmount <= 0) {
                return true; // لا يوجد شيء لفعله
            }

            // يتم إيداع المبلغ المدفوع في خزنة المستخدم (سداد الدين)
            $user->deposit($paidAmount, $cashBoxId);

            $this->createTransaction(
                user: $user,
                type: 'سداد مديونية',
                amount: $paidAmount,
                description: 'سداد جزء من مديونية سابقة',
                cashBoxId: $cashBoxId,
                companyId: $companyId
            );

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * تسجيل عملية دفع قسط أو شراء بالتقسيط من قبل الموظف لنفسه.
     *
     * @param User $user المستخدم المعني.
     * @param float $paidAmount المبلغ المدفوع.
     * @param float $remainingAmount المبلغ المتبقي (الدين).
     * @param int|null $cashBoxId معرف صندوق النقدية.
     * @param int|null $companyId معرف الشركة.
     * @return void
     */
    public function registerInstallmentPayment(User $user, float $paidAmount, float $remainingAmount, ?int $cashBoxId = null, ?int $companyId = null): void
    {
        try {
            $companyId = $companyId ?? $user->company_id;
            // تسجيل دفع القسط (إيداع)
            if ($paidAmount > 0) {
                $user->deposit($paidAmount, $cashBoxId);
                $this->createTransaction(
                    user: $user,
                    type: 'دفع قسط لنفسه',
                    amount: $paidAmount,
                    description: 'دفع قسط أو دفعة من أقساط شراء بالتقسيط من قبل المستخدم لنفسه',
                    cashBoxId: $cashBoxId,
                    companyId: $companyId
                );
            }

            // تسجيل مديونية الأقساط المتبقية
            if ($remainingAmount > 0) {
                $this->createTransaction(
                    user: $user,
                    type: 'مديونية أقساط لنفسه',
                    amount: $remainingAmount,
                    description: 'تسجيل مديونية أقساط متبقية بسبب شراء المستخدم لنفسه بالتقسيط',
                    cashBoxId: $cashBoxId,
                    companyId: $companyId
                );
            }
        } catch (\Throwable $e) {
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
     * @return void
     */
    protected function createTransaction(User $user, string $type, float $amount, string $description, ?int $cashBoxId = null, ?int $companyId = null): void
    {
        try {
            $balanceBefore = $user->balanceBox($cashBoxId); // يجب أن تكون هذه الدالة موجودة في نموذج User
            $balanceAfter  = match ($type) {
                'إيداع', 'سداد مديونية', 'دفع قسط لنفسه' => $balanceBefore + $amount,
                'مديونية', 'مديونية أقساط لنفسه'       => $balanceBefore - $amount,
                default                               => $balanceBefore,
            };

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
            ]);
        } catch (\Throwable $e) {
            Log::error('[UserSelfDebtService] فشل إنشاء المعاملة', ['exception' => $e->getMessage(), 'user_id' => $user->id, 'type' => $type]);
            throw $e;
        }
    }
}

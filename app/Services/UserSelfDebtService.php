<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class UserSelfDebtService
{
    /**
     * تسجيل عملية شراء من قبل الموظف لنفسه
     */
    public function registerPurchase(User $user, float $paidAmount, float $remainingAmount, int $cashBoxId, ?int $companyId = null): void
    {
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
            $this->createTransaction(
                user: $user,
                type: 'مديونية',
                amount: $remainingAmount,
                description: 'تسجيل مديونية بسبب شراء المستخدم لنفسه',
                cashBoxId: $cashBoxId,
                companyId: $companyId
            );
        }
    }

    /**
     * تسجيل عملية سداد مديونية جزئية
     */
    public function payDebt(User $user, float $paidAmount, int $cashBoxId, ?int $companyId = null): void
    {
        $companyId = $companyId ?? $user->company_id;

        if ($paidAmount <= 0) {
            return;
        }

        $user->deposit($paidAmount, $cashBoxId);

        $this->createTransaction(
            user: $user,
            type: 'سداد مديونية',
            amount: $paidAmount,
            description: 'سداد جزء من مديونية سابقة',
            cashBoxId: $cashBoxId,
            companyId: $companyId
        );
    }

    /**
     * تسجيل عملية دفع قسط أو شراء بالتقسيط من قبل الموظف لنفسه
     */
    public function registerInstallmentPayment(User $user, float $paidAmount, float $remainingAmount, int $cashBoxId, ?int $companyId = null): void
    {
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
    }

    /**
     * إنشاء سجل معاملة
     */
    protected function createTransaction(User $user, string $type, float $amount, string $description, int $cashBoxId, int $companyId): void
    {
        $balanceBefore = $user->balanceBox($cashBoxId);
        $balanceAfter  = match ($type) {
            'إيداع'        => $balanceBefore + $amount,
            'مديونية'      => $balanceBefore - $amount,
            'سداد مديونية' => $balanceBefore + $amount,
            default        => $balanceBefore,
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
    }
}

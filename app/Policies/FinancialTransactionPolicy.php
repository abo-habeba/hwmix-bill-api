<?php

namespace App\Policies;

use App\Models\User;
use App\Models\FinancialTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class FinancialTransactionPolicy
{
    use HandlesAuthorization, Scopes;

    /**
     * السماح للمسؤول العام بتجاوز جميع السياسات.
     *
     * @param  \App\Models\User  $user
     * @param  string  $ability
     * @return \Illuminate\Auth\Access\Response|bool|null
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasPermissionTo('admin.super')) {
            return true;
        }
        return null;
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض أي FinancialTransaction.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'financial_transactions.view_all',
            'financial_transactions.view_children',
            'financial_transactions.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ FinancialTransaction المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\FinancialTransaction  $financialTransaction // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, FinancialTransaction $financialTransaction): bool
    {
        if (!$financialTransaction->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('financial_transactions.view_all', $user->company_id) ||
               ($user->hasPermissionTo('financial_transactions.view_children', $user->company_id) && $financialTransaction->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('financial_transactions.view_self', $user->company_id) && $financialTransaction->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء FinancialTransaction.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'financial_transactions.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ FinancialTransaction المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\FinancialTransaction  $financialTransaction // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, FinancialTransaction $financialTransaction): bool
    {
        if (!$financialTransaction->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('financial_transactions.update_all', $user->company_id) ||
               ($user->hasPermissionTo('financial_transactions.update_children', $user->company_id) && $financialTransaction->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('financial_transactions.update_self', $user->company_id) && $financialTransaction->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ FinancialTransaction المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\FinancialTransaction  $financialTransaction // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, FinancialTransaction $financialTransaction): bool
    {
        if (!$financialTransaction->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('financial_transactions.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('financial_transactions.delete_children', $user->company_id) && $financialTransaction->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('financial_transactions.delete_self', $user->company_id) && $financialTransaction->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ FinancialTransaction المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\FinancialTransaction  $financialTransaction // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, FinancialTransaction $financialTransaction): bool
    {
        if (!$financialTransaction->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('financial_transactions.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('financial_transactions.restore_children', $user->company_id) && $financialTransaction->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('financial_transactions.restore_self', $user->company_id) && $financialTransaction->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ FinancialTransaction المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\FinancialTransaction  $financialTransaction // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, FinancialTransaction $financialTransaction): bool
    {
        if (!$financialTransaction->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('financial_transactions.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('financial_transactions.force_delete_children', $user->company_id) && $financialTransaction->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('financial_transactions.force_delete_self', $user->company_id) && $financialTransaction->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

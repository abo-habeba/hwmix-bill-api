<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class TransactionPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Transaction.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'transactions.view_all',
            'transactions.view_children',
            'transactions.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Transaction المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Transaction  $transaction // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Transaction $transaction): bool
    {
        if (!$transaction->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('transactions.view_all', $user->company_id) ||
               ($user->hasPermissionTo('transactions.view_children', $user->company_id) && $transaction->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('transactions.view_self', $user->company_id) && $transaction->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Transaction.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'transactions.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Transaction المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Transaction  $transaction // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Transaction $transaction): bool
    {
        if (!$transaction->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('transactions.update_all', $user->company_id) ||
               ($user->hasPermissionTo('transactions.update_children', $user->company_id) && $transaction->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('transactions.update_self', $user->company_id) && $transaction->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Transaction المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Transaction  $transaction // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        if (!$transaction->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('transactions.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('transactions.delete_children', $user->company_id) && $transaction->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('transactions.delete_self', $user->company_id) && $transaction->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Transaction المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Transaction  $transaction // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Transaction $transaction): bool
    {
        if (!$transaction->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('transactions.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('transactions.restore_children', $user->company_id) && $transaction->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('transactions.restore_self', $user->company_id) && $transaction->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Transaction المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Transaction  $transaction // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Transaction $transaction): bool
    {
        if (!$transaction->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('transactions.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('transactions.force_delete_children', $user->company_id) && $transaction->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('transactions.force_delete_self', $user->company_id) && $transaction->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

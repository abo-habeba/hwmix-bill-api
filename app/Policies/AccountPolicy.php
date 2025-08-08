<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Account;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class AccountPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Account.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'accounts.view_all',
            'accounts.view_children',
            'accounts.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Account المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Account  $account // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Account $account): bool
    {
        if (!$account->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('accounts.view_all', $user->company_id) ||
               ($user->hasPermissionTo('accounts.view_children', $user->company_id) && $account->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('accounts.view_self', $user->company_id) && $account->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Account.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'accounts.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Account المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Account  $account // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Account $account): bool
    {
        if (!$account->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('accounts.update_all', $user->company_id) ||
               ($user->hasPermissionTo('accounts.update_children', $user->company_id) && $account->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('accounts.update_self', $user->company_id) && $account->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Account المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Account  $account // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Account $account): bool
    {
        if (!$account->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('accounts.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('accounts.delete_children', $user->company_id) && $account->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('accounts.delete_self', $user->company_id) && $account->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Account المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Account  $account // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Account $account): bool
    {
        if (!$account->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('accounts.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('accounts.restore_children', $user->company_id) && $account->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('accounts.restore_self', $user->company_id) && $account->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Account المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Account  $account // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Account $account): bool
    {
        if (!$account->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('accounts.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('accounts.force_delete_children', $user->company_id) && $account->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('accounts.force_delete_self', $user->company_id) && $account->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

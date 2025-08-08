<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Installment;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class InstallmentPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Installment.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'installments.view_all',
            'installments.view_children',
            'installments.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Installment المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Installment  $installment // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Installment $installment): bool
    {
        if (!$installment->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('installments.view_all', $user->company_id) ||
               ($user->hasPermissionTo('installments.view_children', $user->company_id) && $installment->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installments.view_self', $user->company_id) && $installment->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Installment.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'installments.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Installment المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Installment  $installment // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Installment $installment): bool
    {
        if (!$installment->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('installments.update_all', $user->company_id) ||
               ($user->hasPermissionTo('installments.update_children', $user->company_id) && $installment->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installments.update_self', $user->company_id) && $installment->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Installment المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Installment  $installment // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Installment $installment): bool
    {
        if (!$installment->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('installments.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('installments.delete_children', $user->company_id) && $installment->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installments.delete_self', $user->company_id) && $installment->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Installment المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Installment  $installment // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Installment $installment): bool
    {
        if (!$installment->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('installments.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('installments.restore_children', $user->company_id) && $installment->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installments.restore_self', $user->company_id) && $installment->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Installment المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Installment  $installment // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Installment $installment): bool
    {
        if (!$installment->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('installments.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('installments.force_delete_children', $user->company_id) && $installment->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installments.force_delete_self', $user->company_id) && $installment->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

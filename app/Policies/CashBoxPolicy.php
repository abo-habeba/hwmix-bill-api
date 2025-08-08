<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CashBox;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class CashBoxPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي CashBox.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'cash_boxs.view_all',
            'cash_boxs.view_children',
            'cash_boxs.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ CashBox المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CashBox  $cashBox // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, CashBox $cashBox): bool
    {
        if (!$cashBox->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('cash_boxs.view_all', $user->company_id) ||
               ($user->hasPermissionTo('cash_boxs.view_children', $user->company_id) && $cashBox->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('cash_boxs.view_self', $user->company_id) && $cashBox->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء CashBox.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'cash_boxs.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ CashBox المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CashBox  $cashBox // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, CashBox $cashBox): bool
    {
        if (!$cashBox->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('cash_boxs.update_all', $user->company_id) ||
               ($user->hasPermissionTo('cash_boxs.update_children', $user->company_id) && $cashBox->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('cash_boxs.update_self', $user->company_id) && $cashBox->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ CashBox المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CashBox  $cashBox // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, CashBox $cashBox): bool
    {
        if (!$cashBox->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('cash_boxs.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('cash_boxs.delete_children', $user->company_id) && $cashBox->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('cash_boxs.delete_self', $user->company_id) && $cashBox->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ CashBox المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CashBox  $cashBox // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, CashBox $cashBox): bool
    {
        if (!$cashBox->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('cash_boxs.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('cash_boxs.restore_children', $user->company_id) && $cashBox->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('cash_boxs.restore_self', $user->company_id) && $cashBox->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ CashBox المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CashBox  $cashBox // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, CashBox $cashBox): bool
    {
        if (!$cashBox->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('cash_boxs.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('cash_boxs.force_delete_children', $user->company_id) && $cashBox->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('cash_boxs.force_delete_self', $user->company_id) && $cashBox->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

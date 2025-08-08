<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CashBoxType;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class CashBoxTypePolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي CashBoxType.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'cash_box_types.view_all',
            'cash_box_types.view_children',
            'cash_box_types.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ CashBoxType المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CashBoxType  $cashBoxType // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, CashBoxType $cashBoxType): bool
    {
        if (!$cashBoxType->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('cash_box_types.view_all', $user->company_id) ||
               ($user->hasPermissionTo('cash_box_types.view_children', $user->company_id) && $cashBoxType->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('cash_box_types.view_self', $user->company_id) && $cashBoxType->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء CashBoxType.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'cash_box_types.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ CashBoxType المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CashBoxType  $cashBoxType // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, CashBoxType $cashBoxType): bool
    {
        if (!$cashBoxType->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('cash_box_types.update_all', $user->company_id) ||
               ($user->hasPermissionTo('cash_box_types.update_children', $user->company_id) && $cashBoxType->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('cash_box_types.update_self', $user->company_id) && $cashBoxType->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ CashBoxType المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CashBoxType  $cashBoxType // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, CashBoxType $cashBoxType): bool
    {
        if (!$cashBoxType->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('cash_box_types.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('cash_box_types.delete_children', $user->company_id) && $cashBoxType->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('cash_box_types.delete_self', $user->company_id) && $cashBoxType->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ CashBoxType المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CashBoxType  $cashBoxType // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, CashBoxType $cashBoxType): bool
    {
        if (!$cashBoxType->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('cash_box_types.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('cash_box_types.restore_children', $user->company_id) && $cashBoxType->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('cash_box_types.restore_self', $user->company_id) && $cashBoxType->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ CashBoxType المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\CashBoxType  $cashBoxType // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, CashBoxType $cashBoxType): bool
    {
        if (!$cashBoxType->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('cash_box_types.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('cash_box_types.force_delete_children', $user->company_id) && $cashBoxType->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('cash_box_types.force_delete_self', $user->company_id) && $cashBoxType->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

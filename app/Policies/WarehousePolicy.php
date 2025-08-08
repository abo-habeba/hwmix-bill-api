<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class WarehousePolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Warehouse.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'warehouses.view_all',
            'warehouses.view_children',
            'warehouses.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Warehouse المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Warehouse  $warehouse // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Warehouse $warehouse): bool
    {
        if (!$warehouse->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('warehouses.view_all', $user->company_id) ||
               ($user->hasPermissionTo('warehouses.view_children', $user->company_id) && $warehouse->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('warehouses.view_self', $user->company_id) && $warehouse->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Warehouse.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'warehouses.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Warehouse المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Warehouse  $warehouse // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Warehouse $warehouse): bool
    {
        if (!$warehouse->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('warehouses.update_all', $user->company_id) ||
               ($user->hasPermissionTo('warehouses.update_children', $user->company_id) && $warehouse->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('warehouses.update_self', $user->company_id) && $warehouse->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Warehouse المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Warehouse  $warehouse // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Warehouse $warehouse): bool
    {
        if (!$warehouse->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('warehouses.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('warehouses.delete_children', $user->company_id) && $warehouse->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('warehouses.delete_self', $user->company_id) && $warehouse->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Warehouse المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Warehouse  $warehouse // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Warehouse $warehouse): bool
    {
        if (!$warehouse->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('warehouses.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('warehouses.restore_children', $user->company_id) && $warehouse->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('warehouses.restore_self', $user->company_id) && $warehouse->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Warehouse المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Warehouse  $warehouse // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Warehouse $warehouse): bool
    {
        if (!$warehouse->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('warehouses.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('warehouses.force_delete_children', $user->company_id) && $warehouse->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('warehouses.force_delete_self', $user->company_id) && $warehouse->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

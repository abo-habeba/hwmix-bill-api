<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class RolePolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Role.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'roles.view_all',
            'roles.view_children',
            'roles.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Role المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Role  $role // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Role $role): bool
    {
        if (!$role->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('roles.view_all', $user->company_id) ||
               ($user->hasPermissionTo('roles.view_children', $user->company_id) && $role->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('roles.view_self', $user->company_id) && $role->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Role.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'roles.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Role المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Role  $role // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Role $role): bool
    {
        if (!$role->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('roles.update_all', $user->company_id) ||
               ($user->hasPermissionTo('roles.update_children', $user->company_id) && $role->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('roles.update_self', $user->company_id) && $role->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Role المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Role  $role // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Role $role): bool
    {
        if (!$role->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('roles.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('roles.delete_children', $user->company_id) && $role->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('roles.delete_self', $user->company_id) && $role->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Role المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Role  $role // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Role $role): bool
    {
        if (!$role->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('roles.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('roles.restore_children', $user->company_id) && $role->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('roles.restore_self', $user->company_id) && $role->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Role المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Role  $role // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Role $role): bool
    {
        if (!$role->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('roles.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('roles.force_delete_children', $user->company_id) && $role->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('roles.force_delete_self', $user->company_id) && $role->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\RoleCompany;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class RoleCompanyPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي RoleCompany.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'role_companys.view_all',
            'role_companys.view_children',
            'role_companys.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ RoleCompany المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\RoleCompany  $roleCompany // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, RoleCompany $roleCompany): bool
    {
        if (!$roleCompany->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('role_companys.view_all', $user->company_id) ||
               ($user->hasPermissionTo('role_companys.view_children', $user->company_id) && $roleCompany->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('role_companys.view_self', $user->company_id) && $roleCompany->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء RoleCompany.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'role_companys.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ RoleCompany المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\RoleCompany  $roleCompany // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, RoleCompany $roleCompany): bool
    {
        if (!$roleCompany->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('role_companys.update_all', $user->company_id) ||
               ($user->hasPermissionTo('role_companys.update_children', $user->company_id) && $roleCompany->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('role_companys.update_self', $user->company_id) && $roleCompany->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ RoleCompany المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\RoleCompany  $roleCompany // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, RoleCompany $roleCompany): bool
    {
        if (!$roleCompany->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('role_companys.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('role_companys.delete_children', $user->company_id) && $roleCompany->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('role_companys.delete_self', $user->company_id) && $roleCompany->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ RoleCompany المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\RoleCompany  $roleCompany // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, RoleCompany $roleCompany): bool
    {
        if (!$roleCompany->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('role_companys.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('role_companys.restore_children', $user->company_id) && $roleCompany->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('role_companys.restore_self', $user->company_id) && $roleCompany->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ RoleCompany المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\RoleCompany  $roleCompany // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, RoleCompany $roleCompany): bool
    {
        if (!$roleCompany->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('role_companys.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('role_companys.force_delete_children', $user->company_id) && $roleCompany->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('role_companys.force_delete_self', $user->company_id) && $roleCompany->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

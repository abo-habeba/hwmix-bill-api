<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Brand;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class BrandPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Brand.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'brands.view_all',
            'brands.view_children',
            'brands.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Brand المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Brand  $brand // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Brand $brand): bool
    {
        if (!$brand->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('brands.view_all', $user->company_id) ||
               ($user->hasPermissionTo('brands.view_children', $user->company_id) && $brand->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('brands.view_self', $user->company_id) && $brand->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Brand.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'brands.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Brand المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Brand  $brand // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Brand $brand): bool
    {
        if (!$brand->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('brands.update_all', $user->company_id) ||
               ($user->hasPermissionTo('brands.update_children', $user->company_id) && $brand->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('brands.update_self', $user->company_id) && $brand->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Brand المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Brand  $brand // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Brand $brand): bool
    {
        if (!$brand->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('brands.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('brands.delete_children', $user->company_id) && $brand->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('brands.delete_self', $user->company_id) && $brand->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Brand المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Brand  $brand // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Brand $brand): bool
    {
        if (!$brand->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('brands.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('brands.restore_children', $user->company_id) && $brand->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('brands.restore_self', $user->company_id) && $brand->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Brand المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Brand  $brand // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Brand $brand): bool
    {
        if (!$brand->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('brands.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('brands.force_delete_children', $user->company_id) && $brand->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('brands.force_delete_self', $user->company_id) && $brand->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

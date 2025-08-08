<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Category;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class CategoryPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Category.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'categorys.view_all',
            'categorys.view_children',
            'categorys.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Category المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Category  $category // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Category $category): bool
    {
        if (!$category->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('categorys.view_all', $user->company_id) ||
               ($user->hasPermissionTo('categorys.view_children', $user->company_id) && $category->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('categorys.view_self', $user->company_id) && $category->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Category.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'categorys.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Category المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Category  $category // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Category $category): bool
    {
        if (!$category->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('categorys.update_all', $user->company_id) ||
               ($user->hasPermissionTo('categorys.update_children', $user->company_id) && $category->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('categorys.update_self', $user->company_id) && $category->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Category المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Category  $category // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Category $category): bool
    {
        if (!$category->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('categorys.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('categorys.delete_children', $user->company_id) && $category->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('categorys.delete_self', $user->company_id) && $category->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Category المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Category  $category // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Category $category): bool
    {
        if (!$category->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('categorys.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('categorys.restore_children', $user->company_id) && $category->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('categorys.restore_self', $user->company_id) && $category->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Category المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Category  $category // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Category $category): bool
    {
        if (!$category->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('categorys.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('categorys.force_delete_children', $user->company_id) && $category->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('categorys.force_delete_self', $user->company_id) && $category->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class ProductPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Product.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'products.view_all',
            'products.view_children',
            'products.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Product المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Product  $product // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Product $product): bool
    {
        if (!$product->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('products.view_all', $user->company_id) ||
               ($user->hasPermissionTo('products.view_children', $user->company_id) && $product->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('products.view_self', $user->company_id) && $product->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Product.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'products.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Product المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Product  $product // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Product $product): bool
    {
        if (!$product->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('products.update_all', $user->company_id) ||
               ($user->hasPermissionTo('products.update_children', $user->company_id) && $product->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('products.update_self', $user->company_id) && $product->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Product المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Product  $product // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Product $product): bool
    {
        if (!$product->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('products.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('products.delete_children', $user->company_id) && $product->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('products.delete_self', $user->company_id) && $product->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Product المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Product  $product // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Product $product): bool
    {
        if (!$product->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('products.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('products.restore_children', $user->company_id) && $product->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('products.restore_self', $user->company_id) && $product->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Product المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Product  $product // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Product $product): bool
    {
        if (!$product->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('products.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('products.force_delete_children', $user->company_id) && $product->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('products.force_delete_self', $user->company_id) && $product->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

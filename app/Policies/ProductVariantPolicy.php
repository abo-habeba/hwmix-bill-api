<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ProductVariant;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class ProductVariantPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي ProductVariant.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'product_variants.view_all',
            'product_variants.view_children',
            'product_variants.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ ProductVariant المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ProductVariant  $productVariant // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, ProductVariant $productVariant): bool
    {
        if (!$productVariant->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('product_variants.view_all', $user->company_id) ||
               ($user->hasPermissionTo('product_variants.view_children', $user->company_id) && $productVariant->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('product_variants.view_self', $user->company_id) && $productVariant->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء ProductVariant.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'product_variants.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ ProductVariant المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ProductVariant  $productVariant // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, ProductVariant $productVariant): bool
    {
        if (!$productVariant->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('product_variants.update_all', $user->company_id) ||
               ($user->hasPermissionTo('product_variants.update_children', $user->company_id) && $productVariant->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('product_variants.update_self', $user->company_id) && $productVariant->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ ProductVariant المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ProductVariant  $productVariant // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ProductVariant $productVariant): bool
    {
        if (!$productVariant->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('product_variants.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('product_variants.delete_children', $user->company_id) && $productVariant->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('product_variants.delete_self', $user->company_id) && $productVariant->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ ProductVariant المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ProductVariant  $productVariant // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, ProductVariant $productVariant): bool
    {
        if (!$productVariant->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('product_variants.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('product_variants.restore_children', $user->company_id) && $productVariant->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('product_variants.restore_self', $user->company_id) && $productVariant->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ ProductVariant المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ProductVariant  $productVariant // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, ProductVariant $productVariant): bool
    {
        if (!$productVariant->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('product_variants.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('product_variants.force_delete_children', $user->company_id) && $productVariant->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('product_variants.force_delete_self', $user->company_id) && $productVariant->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

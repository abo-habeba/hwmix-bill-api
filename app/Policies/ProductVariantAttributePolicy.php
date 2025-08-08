<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ProductVariantAttribute;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class ProductVariantAttributePolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي ProductVariantAttribute.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'product_variant_attributes.view_all',
            'product_variant_attributes.view_children',
            'product_variant_attributes.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ ProductVariantAttribute المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ProductVariantAttribute  $productVariantAttribute // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, ProductVariantAttribute $productVariantAttribute): bool
    {
        if (!$productVariantAttribute->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('product_variant_attributes.view_all', $user->company_id) ||
               ($user->hasPermissionTo('product_variant_attributes.view_children', $user->company_id) && $productVariantAttribute->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('product_variant_attributes.view_self', $user->company_id) && $productVariantAttribute->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء ProductVariantAttribute.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'product_variant_attributes.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ ProductVariantAttribute المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ProductVariantAttribute  $productVariantAttribute // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, ProductVariantAttribute $productVariantAttribute): bool
    {
        if (!$productVariantAttribute->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('product_variant_attributes.update_all', $user->company_id) ||
               ($user->hasPermissionTo('product_variant_attributes.update_children', $user->company_id) && $productVariantAttribute->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('product_variant_attributes.update_self', $user->company_id) && $productVariantAttribute->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ ProductVariantAttribute المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ProductVariantAttribute  $productVariantAttribute // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ProductVariantAttribute $productVariantAttribute): bool
    {
        if (!$productVariantAttribute->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('product_variant_attributes.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('product_variant_attributes.delete_children', $user->company_id) && $productVariantAttribute->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('product_variant_attributes.delete_self', $user->company_id) && $productVariantAttribute->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ ProductVariantAttribute المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ProductVariantAttribute  $productVariantAttribute // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, ProductVariantAttribute $productVariantAttribute): bool
    {
        if (!$productVariantAttribute->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('product_variant_attributes.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('product_variant_attributes.restore_children', $user->company_id) && $productVariantAttribute->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('product_variant_attributes.restore_self', $user->company_id) && $productVariantAttribute->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ ProductVariantAttribute المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ProductVariantAttribute  $productVariantAttribute // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, ProductVariantAttribute $productVariantAttribute): bool
    {
        if (!$productVariantAttribute->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('product_variant_attributes.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('product_variant_attributes.force_delete_children', $user->company_id) && $productVariantAttribute->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('product_variant_attributes.force_delete_self', $user->company_id) && $productVariantAttribute->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

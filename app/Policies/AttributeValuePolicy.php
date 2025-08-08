<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AttributeValue;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class AttributeValuePolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي AttributeValue.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'attribute_values.view_all',
            'attribute_values.view_children',
            'attribute_values.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ AttributeValue المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AttributeValue  $attributeValue // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, AttributeValue $attributeValue): bool
    {
        if (!$attributeValue->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('attribute_values.view_all', $user->company_id) ||
               ($user->hasPermissionTo('attribute_values.view_children', $user->company_id) && $attributeValue->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('attribute_values.view_self', $user->company_id) && $attributeValue->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء AttributeValue.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'attribute_values.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ AttributeValue المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AttributeValue  $attributeValue // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, AttributeValue $attributeValue): bool
    {
        if (!$attributeValue->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('attribute_values.update_all', $user->company_id) ||
               ($user->hasPermissionTo('attribute_values.update_children', $user->company_id) && $attributeValue->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('attribute_values.update_self', $user->company_id) && $attributeValue->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ AttributeValue المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AttributeValue  $attributeValue // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, AttributeValue $attributeValue): bool
    {
        if (!$attributeValue->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('attribute_values.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('attribute_values.delete_children', $user->company_id) && $attributeValue->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('attribute_values.delete_self', $user->company_id) && $attributeValue->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ AttributeValue المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AttributeValue  $attributeValue // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, AttributeValue $attributeValue): bool
    {
        if (!$attributeValue->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('attribute_values.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('attribute_values.restore_children', $user->company_id) && $attributeValue->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('attribute_values.restore_self', $user->company_id) && $attributeValue->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ AttributeValue المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\AttributeValue  $attributeValue // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, AttributeValue $attributeValue): bool
    {
        if (!$attributeValue->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('attribute_values.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('attribute_values.force_delete_children', $user->company_id) && $attributeValue->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('attribute_values.force_delete_self', $user->company_id) && $attributeValue->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

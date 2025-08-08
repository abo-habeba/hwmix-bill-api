<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Attribute;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class AttributePolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Attribute.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'attributes.view_all',
            'attributes.view_children',
            'attributes.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Attribute المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Attribute  $attribute // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Attribute $attribute): bool
    {
        if (!$attribute->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('attributes.view_all', $user->company_id) ||
               ($user->hasPermissionTo('attributes.view_children', $user->company_id) && $attribute->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('attributes.view_self', $user->company_id) && $attribute->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Attribute.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'attributes.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Attribute المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Attribute  $attribute // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Attribute $attribute): bool
    {
        if (!$attribute->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('attributes.update_all', $user->company_id) ||
               ($user->hasPermissionTo('attributes.update_children', $user->company_id) && $attribute->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('attributes.update_self', $user->company_id) && $attribute->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Attribute المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Attribute  $attribute // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Attribute $attribute): bool
    {
        if (!$attribute->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('attributes.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('attributes.delete_children', $user->company_id) && $attribute->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('attributes.delete_self', $user->company_id) && $attribute->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Attribute المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Attribute  $attribute // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Attribute $attribute): bool
    {
        if (!$attribute->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('attributes.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('attributes.restore_children', $user->company_id) && $attribute->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('attributes.restore_self', $user->company_id) && $attribute->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Attribute المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Attribute  $attribute // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Attribute $attribute): bool
    {
        if (!$attribute->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('attributes.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('attributes.force_delete_children', $user->company_id) && $attribute->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('attributes.force_delete_self', $user->company_id) && $attribute->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

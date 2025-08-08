<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Translation;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class TranslationPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Translation.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'translations.view_all',
            'translations.view_children',
            'translations.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Translation المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Translation  $translation // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Translation $translation): bool
    {
        if (!$translation->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('translations.view_all', $user->company_id) ||
               ($user->hasPermissionTo('translations.view_children', $user->company_id) && $translation->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('translations.view_self', $user->company_id) && $translation->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Translation.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'translations.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Translation المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Translation  $translation // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Translation $translation): bool
    {
        if (!$translation->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('translations.update_all', $user->company_id) ||
               ($user->hasPermissionTo('translations.update_children', $user->company_id) && $translation->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('translations.update_self', $user->company_id) && $translation->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Translation المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Translation  $translation // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Translation $translation): bool
    {
        if (!$translation->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('translations.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('translations.delete_children', $user->company_id) && $translation->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('translations.delete_self', $user->company_id) && $translation->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Translation المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Translation  $translation // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Translation $translation): bool
    {
        if (!$translation->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('translations.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('translations.restore_children', $user->company_id) && $translation->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('translations.restore_self', $user->company_id) && $translation->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Translation المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Translation  $translation // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Translation $translation): bool
    {
        if (!$translation->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('translations.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('translations.force_delete_children', $user->company_id) && $translation->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('translations.force_delete_self', $user->company_id) && $translation->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

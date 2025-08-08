<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Company;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class CompanyPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Company.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'companys.view_all',
            'companys.view_children',
            'companys.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Company المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Company  $company // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Company $company): bool
    {
        if (!$company->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('companys.view_all', $user->company_id) ||
               ($user->hasPermissionTo('companys.view_children', $user->company_id) && $company->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('companys.view_self', $user->company_id) && $company->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Company.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'companys.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Company المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Company  $company // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Company $company): bool
    {
        if (!$company->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('companys.update_all', $user->company_id) ||
               ($user->hasPermissionTo('companys.update_children', $user->company_id) && $company->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('companys.update_self', $user->company_id) && $company->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Company المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Company  $company // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Company $company): bool
    {
        if (!$company->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('companys.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('companys.delete_children', $user->company_id) && $company->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('companys.delete_self', $user->company_id) && $company->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Company المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Company  $company // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Company $company): bool
    {
        if (!$company->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('companys.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('companys.restore_children', $user->company_id) && $company->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('companys.restore_self', $user->company_id) && $company->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Company المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Company  $company // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Company $company): bool
    {
        if (!$company->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('companys.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('companys.force_delete_children', $user->company_id) && $company->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('companys.force_delete_self', $user->company_id) && $company->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

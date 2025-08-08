<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InstallmentPlan;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class InstallmentPlanPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي InstallmentPlan.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'installment_plans.view_all',
            'installment_plans.view_children',
            'installment_plans.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ InstallmentPlan المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InstallmentPlan  $installmentPlan // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, InstallmentPlan $installmentPlan): bool
    {
        if (!$installmentPlan->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('installment_plans.view_all', $user->company_id) ||
               ($user->hasPermissionTo('installment_plans.view_children', $user->company_id) && $installmentPlan->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installment_plans.view_self', $user->company_id) && $installmentPlan->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء InstallmentPlan.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'installment_plans.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ InstallmentPlan المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InstallmentPlan  $installmentPlan // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, InstallmentPlan $installmentPlan): bool
    {
        if (!$installmentPlan->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('installment_plans.update_all', $user->company_id) ||
               ($user->hasPermissionTo('installment_plans.update_children', $user->company_id) && $installmentPlan->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installment_plans.update_self', $user->company_id) && $installmentPlan->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ InstallmentPlan المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InstallmentPlan  $installmentPlan // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, InstallmentPlan $installmentPlan): bool
    {
        if (!$installmentPlan->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('installment_plans.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('installment_plans.delete_children', $user->company_id) && $installmentPlan->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installment_plans.delete_self', $user->company_id) && $installmentPlan->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ InstallmentPlan المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InstallmentPlan  $installmentPlan // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, InstallmentPlan $installmentPlan): bool
    {
        if (!$installmentPlan->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('installment_plans.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('installment_plans.restore_children', $user->company_id) && $installmentPlan->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installment_plans.restore_self', $user->company_id) && $installmentPlan->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ InstallmentPlan المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InstallmentPlan  $installmentPlan // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, InstallmentPlan $installmentPlan): bool
    {
        if (!$installmentPlan->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('installment_plans.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('installment_plans.force_delete_children', $user->company_id) && $installmentPlan->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installment_plans.force_delete_self', $user->company_id) && $installmentPlan->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

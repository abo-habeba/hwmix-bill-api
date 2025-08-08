<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Plan;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class PlanPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Plan.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'plans.view_all',
            'plans.view_children',
            'plans.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Plan المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Plan  $plan // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Plan $plan): bool
    {
        if (!$plan->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('plans.view_all', $user->company_id) ||
               ($user->hasPermissionTo('plans.view_children', $user->company_id) && $plan->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('plans.view_self', $user->company_id) && $plan->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Plan.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'plans.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Plan المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Plan  $plan // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Plan $plan): bool
    {
        if (!$plan->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('plans.update_all', $user->company_id) ||
               ($user->hasPermissionTo('plans.update_children', $user->company_id) && $plan->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('plans.update_self', $user->company_id) && $plan->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Plan المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Plan  $plan // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Plan $plan): bool
    {
        if (!$plan->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('plans.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('plans.delete_children', $user->company_id) && $plan->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('plans.delete_self', $user->company_id) && $plan->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Plan المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Plan  $plan // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Plan $plan): bool
    {
        if (!$plan->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('plans.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('plans.restore_children', $user->company_id) && $plan->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('plans.restore_self', $user->company_id) && $plan->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Plan المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Plan  $plan // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Plan $plan): bool
    {
        if (!$plan->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('plans.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('plans.force_delete_children', $user->company_id) && $plan->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('plans.force_delete_self', $user->company_id) && $plan->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

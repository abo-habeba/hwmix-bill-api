<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class ActivityLogPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي ActivityLog.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'activity_logs.view_all',
            'activity_logs.view_children',
            'activity_logs.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ ActivityLog المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ActivityLog  $activityLog // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, ActivityLog $activityLog): bool
    {
        if (!$activityLog->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('activity_logs.view_all', $user->company_id) ||
               ($user->hasPermissionTo('activity_logs.view_children', $user->company_id) && $activityLog->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('activity_logs.view_self', $user->company_id) && $activityLog->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء ActivityLog.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'activity_logs.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ ActivityLog المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ActivityLog  $activityLog // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, ActivityLog $activityLog): bool
    {
        if (!$activityLog->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('activity_logs.update_all', $user->company_id) ||
               ($user->hasPermissionTo('activity_logs.update_children', $user->company_id) && $activityLog->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('activity_logs.update_self', $user->company_id) && $activityLog->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ ActivityLog المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ActivityLog  $activityLog // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ActivityLog $activityLog): bool
    {
        if (!$activityLog->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('activity_logs.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('activity_logs.delete_children', $user->company_id) && $activityLog->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('activity_logs.delete_self', $user->company_id) && $activityLog->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ ActivityLog المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ActivityLog  $activityLog // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, ActivityLog $activityLog): bool
    {
        if (!$activityLog->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('activity_logs.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('activity_logs.restore_children', $user->company_id) && $activityLog->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('activity_logs.restore_self', $user->company_id) && $activityLog->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ ActivityLog المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\ActivityLog  $activityLog // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, ActivityLog $activityLog): bool
    {
        if (!$activityLog->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('activity_logs.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('activity_logs.force_delete_children', $user->company_id) && $activityLog->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('activity_logs.force_delete_self', $user->company_id) && $activityLog->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

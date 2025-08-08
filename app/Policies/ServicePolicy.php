<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Service;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class ServicePolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Service.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'services.view_all',
            'services.view_children',
            'services.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Service المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Service  $service // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Service $service): bool
    {
        if (!$service->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('services.view_all', $user->company_id) ||
               ($user->hasPermissionTo('services.view_children', $user->company_id) && $service->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('services.view_self', $user->company_id) && $service->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Service.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'services.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Service المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Service  $service // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Service $service): bool
    {
        if (!$service->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('services.update_all', $user->company_id) ||
               ($user->hasPermissionTo('services.update_children', $user->company_id) && $service->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('services.update_self', $user->company_id) && $service->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Service المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Service  $service // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Service $service): bool
    {
        if (!$service->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('services.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('services.delete_children', $user->company_id) && $service->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('services.delete_self', $user->company_id) && $service->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Service المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Service  $service // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Service $service): bool
    {
        if (!$service->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('services.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('services.restore_children', $user->company_id) && $service->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('services.restore_self', $user->company_id) && $service->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Service المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Service  $service // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Service $service): bool
    {
        if (!$service->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('services.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('services.force_delete_children', $user->company_id) && $service->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('services.force_delete_self', $user->company_id) && $service->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

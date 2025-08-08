<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class SubscriptionPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Subscription.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'subscriptions.view_all',
            'subscriptions.view_children',
            'subscriptions.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Subscription المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Subscription  $subscription // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Subscription $subscription): bool
    {
        if (!$subscription->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('subscriptions.view_all', $user->company_id) ||
               ($user->hasPermissionTo('subscriptions.view_children', $user->company_id) && $subscription->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('subscriptions.view_self', $user->company_id) && $subscription->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Subscription.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'subscriptions.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Subscription المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Subscription  $subscription // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Subscription $subscription): bool
    {
        if (!$subscription->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('subscriptions.update_all', $user->company_id) ||
               ($user->hasPermissionTo('subscriptions.update_children', $user->company_id) && $subscription->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('subscriptions.update_self', $user->company_id) && $subscription->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Subscription المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Subscription  $subscription // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        if (!$subscription->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('subscriptions.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('subscriptions.delete_children', $user->company_id) && $subscription->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('subscriptions.delete_self', $user->company_id) && $subscription->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Subscription المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Subscription  $subscription // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Subscription $subscription): bool
    {
        if (!$subscription->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('subscriptions.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('subscriptions.restore_children', $user->company_id) && $subscription->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('subscriptions.restore_self', $user->company_id) && $subscription->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Subscription المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Subscription  $subscription // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Subscription $subscription): bool
    {
        if (!$subscription->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('subscriptions.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('subscriptions.force_delete_children', $user->company_id) && $subscription->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('subscriptions.force_delete_self', $user->company_id) && $subscription->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

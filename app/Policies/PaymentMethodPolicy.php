<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PaymentMethod;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class PaymentMethodPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي PaymentMethod.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'payment_methods.view_all',
            'payment_methods.view_children',
            'payment_methods.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ PaymentMethod المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentMethod  $paymentMethod // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        if (!$paymentMethod->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('payment_methods.view_all', $user->company_id) ||
               ($user->hasPermissionTo('payment_methods.view_children', $user->company_id) && $paymentMethod->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('payment_methods.view_self', $user->company_id) && $paymentMethod->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء PaymentMethod.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'payment_methods.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ PaymentMethod المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentMethod  $paymentMethod // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        if (!$paymentMethod->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('payment_methods.update_all', $user->company_id) ||
               ($user->hasPermissionTo('payment_methods.update_children', $user->company_id) && $paymentMethod->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('payment_methods.update_self', $user->company_id) && $paymentMethod->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ PaymentMethod المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentMethod  $paymentMethod // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        if (!$paymentMethod->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('payment_methods.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('payment_methods.delete_children', $user->company_id) && $paymentMethod->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('payment_methods.delete_self', $user->company_id) && $paymentMethod->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ PaymentMethod المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentMethod  $paymentMethod // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, PaymentMethod $paymentMethod): bool
    {
        if (!$paymentMethod->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('payment_methods.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('payment_methods.restore_children', $user->company_id) && $paymentMethod->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('payment_methods.restore_self', $user->company_id) && $paymentMethod->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ PaymentMethod المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentMethod  $paymentMethod // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, PaymentMethod $paymentMethod): bool
    {
        if (!$paymentMethod->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('payment_methods.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('payment_methods.force_delete_children', $user->company_id) && $paymentMethod->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('payment_methods.force_delete_self', $user->company_id) && $paymentMethod->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

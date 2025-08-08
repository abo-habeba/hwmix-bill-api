<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Payment;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class PaymentPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Payment.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'payments.view_all',
            'payments.view_children',
            'payments.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Payment المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Payment $payment): bool
    {
        if (!$payment->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('payments.view_all', $user->company_id) ||
               ($user->hasPermissionTo('payments.view_children', $user->company_id) && $payment->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('payments.view_self', $user->company_id) && $payment->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Payment.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'payments.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Payment المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Payment $payment): bool
    {
        if (!$payment->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('payments.update_all', $user->company_id) ||
               ($user->hasPermissionTo('payments.update_children', $user->company_id) && $payment->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('payments.update_self', $user->company_id) && $payment->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Payment المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Payment $payment): bool
    {
        if (!$payment->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('payments.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('payments.delete_children', $user->company_id) && $payment->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('payments.delete_self', $user->company_id) && $payment->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Payment المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Payment $payment): bool
    {
        if (!$payment->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('payments.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('payments.restore_children', $user->company_id) && $payment->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('payments.restore_self', $user->company_id) && $payment->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Payment المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Payment  $payment // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
        if (!$payment->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('payments.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('payments.force_delete_children', $user->company_id) && $payment->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('payments.force_delete_self', $user->company_id) && $payment->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

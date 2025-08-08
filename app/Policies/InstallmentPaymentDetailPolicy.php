<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InstallmentPaymentDetail;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class InstallmentPaymentDetailPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي InstallmentPaymentDetail.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'installment_payment_details.view_all',
            'installment_payment_details.view_children',
            'installment_payment_details.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ InstallmentPaymentDetail المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InstallmentPaymentDetail  $installmentPaymentDetail // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, InstallmentPaymentDetail $installmentPaymentDetail): bool
    {
        if (!$installmentPaymentDetail->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('installment_payment_details.view_all', $user->company_id) ||
               ($user->hasPermissionTo('installment_payment_details.view_children', $user->company_id) && $installmentPaymentDetail->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installment_payment_details.view_self', $user->company_id) && $installmentPaymentDetail->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء InstallmentPaymentDetail.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'installment_payment_details.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ InstallmentPaymentDetail المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InstallmentPaymentDetail  $installmentPaymentDetail // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, InstallmentPaymentDetail $installmentPaymentDetail): bool
    {
        if (!$installmentPaymentDetail->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('installment_payment_details.update_all', $user->company_id) ||
               ($user->hasPermissionTo('installment_payment_details.update_children', $user->company_id) && $installmentPaymentDetail->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installment_payment_details.update_self', $user->company_id) && $installmentPaymentDetail->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ InstallmentPaymentDetail المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InstallmentPaymentDetail  $installmentPaymentDetail // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, InstallmentPaymentDetail $installmentPaymentDetail): bool
    {
        if (!$installmentPaymentDetail->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('installment_payment_details.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('installment_payment_details.delete_children', $user->company_id) && $installmentPaymentDetail->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installment_payment_details.delete_self', $user->company_id) && $installmentPaymentDetail->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ InstallmentPaymentDetail المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InstallmentPaymentDetail  $installmentPaymentDetail // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, InstallmentPaymentDetail $installmentPaymentDetail): bool
    {
        if (!$installmentPaymentDetail->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('installment_payment_details.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('installment_payment_details.restore_children', $user->company_id) && $installmentPaymentDetail->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installment_payment_details.restore_self', $user->company_id) && $installmentPaymentDetail->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ InstallmentPaymentDetail المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InstallmentPaymentDetail  $installmentPaymentDetail // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, InstallmentPaymentDetail $installmentPaymentDetail): bool
    {
        if (!$installmentPaymentDetail->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('installment_payment_details.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('installment_payment_details.force_delete_children', $user->company_id) && $installmentPaymentDetail->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('installment_payment_details.force_delete_self', $user->company_id) && $installmentPaymentDetail->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

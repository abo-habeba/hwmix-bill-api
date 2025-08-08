<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Quotation;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class QuotationPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Quotation.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'quotations.view_all',
            'quotations.view_children',
            'quotations.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Quotation المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Quotation  $quotation // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Quotation $quotation): bool
    {
        if (!$quotation->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('quotations.view_all', $user->company_id) ||
               ($user->hasPermissionTo('quotations.view_children', $user->company_id) && $quotation->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('quotations.view_self', $user->company_id) && $quotation->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Quotation.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'quotations.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Quotation المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Quotation  $quotation // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Quotation $quotation): bool
    {
        if (!$quotation->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('quotations.update_all', $user->company_id) ||
               ($user->hasPermissionTo('quotations.update_children', $user->company_id) && $quotation->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('quotations.update_self', $user->company_id) && $quotation->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Quotation المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Quotation  $quotation // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Quotation $quotation): bool
    {
        if (!$quotation->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('quotations.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('quotations.delete_children', $user->company_id) && $quotation->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('quotations.delete_self', $user->company_id) && $quotation->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Quotation المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Quotation  $quotation // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Quotation $quotation): bool
    {
        if (!$quotation->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('quotations.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('quotations.restore_children', $user->company_id) && $quotation->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('quotations.restore_self', $user->company_id) && $quotation->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Quotation المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Quotation  $quotation // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Quotation $quotation): bool
    {
        if (!$quotation->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('quotations.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('quotations.force_delete_children', $user->company_id) && $quotation->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('quotations.force_delete_self', $user->company_id) && $quotation->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

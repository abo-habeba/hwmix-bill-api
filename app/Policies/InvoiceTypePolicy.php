<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InvoiceType;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class InvoiceTypePolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي InvoiceType.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'invoice_types.view_all',
            'invoice_types.view_children',
            'invoice_types.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ InvoiceType المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InvoiceType  $invoiceType // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, InvoiceType $invoiceType): bool
    {
        if (!$invoiceType->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('invoice_types.view_all', $user->company_id) ||
               ($user->hasPermissionTo('invoice_types.view_children', $user->company_id) && $invoiceType->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoice_types.view_self', $user->company_id) && $invoiceType->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء InvoiceType.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'invoice_types.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ InvoiceType المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InvoiceType  $invoiceType // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, InvoiceType $invoiceType): bool
    {
        if (!$invoiceType->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('invoice_types.update_all', $user->company_id) ||
               ($user->hasPermissionTo('invoice_types.update_children', $user->company_id) && $invoiceType->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoice_types.update_self', $user->company_id) && $invoiceType->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ InvoiceType المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InvoiceType  $invoiceType // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, InvoiceType $invoiceType): bool
    {
        if (!$invoiceType->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('invoice_types.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('invoice_types.delete_children', $user->company_id) && $invoiceType->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoice_types.delete_self', $user->company_id) && $invoiceType->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ InvoiceType المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InvoiceType  $invoiceType // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, InvoiceType $invoiceType): bool
    {
        if (!$invoiceType->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('invoice_types.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('invoice_types.restore_children', $user->company_id) && $invoiceType->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoice_types.restore_self', $user->company_id) && $invoiceType->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ InvoiceType المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InvoiceType  $invoiceType // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, InvoiceType $invoiceType): bool
    {
        if (!$invoiceType->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('invoice_types.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('invoice_types.force_delete_children', $user->company_id) && $invoiceType->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoice_types.force_delete_self', $user->company_id) && $invoiceType->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

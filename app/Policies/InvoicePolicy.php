<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class InvoicePolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Invoice.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'invoices.view_all',
            'invoices.view_children',
            'invoices.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Invoice المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Invoice  $invoice // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if (!$invoice->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('invoices.view_all', $user->company_id) ||
               ($user->hasPermissionTo('invoices.view_children', $user->company_id) && $invoice->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoices.view_self', $user->company_id) && $invoice->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Invoice.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'invoices.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Invoice المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Invoice  $invoice // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Invoice $invoice): bool
    {
        if (!$invoice->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('invoices.update_all', $user->company_id) ||
               ($user->hasPermissionTo('invoices.update_children', $user->company_id) && $invoice->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoices.update_self', $user->company_id) && $invoice->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Invoice المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Invoice  $invoice // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        if (!$invoice->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('invoices.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('invoices.delete_children', $user->company_id) && $invoice->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoices.delete_self', $user->company_id) && $invoice->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Invoice المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Invoice  $invoice // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        if (!$invoice->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('invoices.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('invoices.restore_children', $user->company_id) && $invoice->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoices.restore_self', $user->company_id) && $invoice->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Invoice المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Invoice  $invoice // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        if (!$invoice->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('invoices.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('invoices.force_delete_children', $user->company_id) && $invoice->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoices.force_delete_self', $user->company_id) && $invoice->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

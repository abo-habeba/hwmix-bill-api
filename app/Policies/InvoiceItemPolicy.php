<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InvoiceItem;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class InvoiceItemPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي InvoiceItem.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'invoice_items.view_all',
            'invoice_items.view_children',
            'invoice_items.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ InvoiceItem المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InvoiceItem  $invoiceItem // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, InvoiceItem $invoiceItem): bool
    {
        if (!$invoiceItem->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('invoice_items.view_all', $user->company_id) ||
               ($user->hasPermissionTo('invoice_items.view_children', $user->company_id) && $invoiceItem->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoice_items.view_self', $user->company_id) && $invoiceItem->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء InvoiceItem.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'invoice_items.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ InvoiceItem المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InvoiceItem  $invoiceItem // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, InvoiceItem $invoiceItem): bool
    {
        if (!$invoiceItem->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('invoice_items.update_all', $user->company_id) ||
               ($user->hasPermissionTo('invoice_items.update_children', $user->company_id) && $invoiceItem->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoice_items.update_self', $user->company_id) && $invoiceItem->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ InvoiceItem المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InvoiceItem  $invoiceItem // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, InvoiceItem $invoiceItem): bool
    {
        if (!$invoiceItem->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('invoice_items.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('invoice_items.delete_children', $user->company_id) && $invoiceItem->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoice_items.delete_self', $user->company_id) && $invoiceItem->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ InvoiceItem المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InvoiceItem  $invoiceItem // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, InvoiceItem $invoiceItem): bool
    {
        if (!$invoiceItem->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('invoice_items.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('invoice_items.restore_children', $user->company_id) && $invoiceItem->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoice_items.restore_self', $user->company_id) && $invoiceItem->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ InvoiceItem المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\InvoiceItem  $invoiceItem // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, InvoiceItem $invoiceItem): bool
    {
        if (!$invoiceItem->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('invoice_items.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('invoice_items.force_delete_children', $user->company_id) && $invoiceItem->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('invoice_items.force_delete_self', $user->company_id) && $invoiceItem->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Stock;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class StockPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Stock.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'stocks.view_all',
            'stocks.view_children',
            'stocks.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Stock المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Stock  $stock // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Stock $stock): bool
    {
        if (!$stock->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('stocks.view_all', $user->company_id) ||
               ($user->hasPermissionTo('stocks.view_children', $user->company_id) && $stock->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('stocks.view_self', $user->company_id) && $stock->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Stock.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'stocks.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Stock المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Stock  $stock // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Stock $stock): bool
    {
        if (!$stock->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('stocks.update_all', $user->company_id) ||
               ($user->hasPermissionTo('stocks.update_children', $user->company_id) && $stock->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('stocks.update_self', $user->company_id) && $stock->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Stock المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Stock  $stock // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Stock $stock): bool
    {
        if (!$stock->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('stocks.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('stocks.delete_children', $user->company_id) && $stock->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('stocks.delete_self', $user->company_id) && $stock->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Stock المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Stock  $stock // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Stock $stock): bool
    {
        if (!$stock->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('stocks.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('stocks.restore_children', $user->company_id) && $stock->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('stocks.restore_self', $user->company_id) && $stock->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Stock المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Stock  $stock // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Stock $stock): bool
    {
        if (!$stock->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('stocks.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('stocks.force_delete_children', $user->company_id) && $stock->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('stocks.force_delete_self', $user->company_id) && $stock->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

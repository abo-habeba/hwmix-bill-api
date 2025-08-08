<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class OrderPolicy
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
     * تحديد ما إذا كان المستخدم يمكنه عرض أي Order.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'orders.view_all',
            'orders.view_children',
            'orders.view_self',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ Order المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Order  $order // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Order $order): bool
    {
        if (!$order->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('orders.view_all', $user->company_id) ||
               ($user->hasPermissionTo('orders.view_children', $user->company_id) && $order->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('orders.view_self', $user->company_id) && $order->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء Order.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user): bool
    {
        return $user->hasAnyPermission([
            'orders.create',
            'admin.company',
        ], $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ Order المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Order  $order // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Order $order): bool
    {
        if (!$order->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('orders.update_all', $user->company_id) ||
               ($user->hasPermissionTo('orders.update_children', $user->company_id) && $order->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('orders.update_self', $user->company_id) && $order->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Order المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Order  $order // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Order $order): bool
    {
        if (!$order->belongsToCurrentCompany()) {
            return false;
        }

        return $user->hasPermissionTo('orders.delete_all', $user->company_id) ||
               ($user->hasPermissionTo('orders.delete_children', $user->company_id) && $order->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('orders.delete_self', $user->company_id) && $order->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ Order المحدد.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Order  $order // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Order $order): bool
    {
        if (!$order->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('orders.restore_all', $user->company_id) ||
               ($user->hasPermissionTo('orders.restore_children', $user->company_id) && $order->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('orders.restore_self', $user->company_id) && $order->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ Order المحدد بشكل دائم.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Order  $order // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Order $order): bool
    {
        if (!$order->belongsToCurrentCompany()) {
            return false;
        }
        return $user->hasPermissionTo('orders.force_delete_all', $user->company_id) ||
               ($user->hasPermissionTo('orders.force_delete_children', $user->company_id) && $order->createdByUserOrChildren()) ||
               ($user->hasPermissionTo('orders.force_delete_self', $user->company_id) && $order->createdByCurrentUser()) ||
               $user->hasPermissionTo('admin.company', $user->company_id);
    }
}

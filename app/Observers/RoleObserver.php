<?php

namespace App\Observers;

use App\Models\ActivityLog;
// use App\Models\Role;
use Spatie\Permission\Models\Role;

class RoleObserver
{
    /**
     * Handle the Role "created" event.
     */
    public function created(Role $role): void
    {
        ActivityLog::create([
            'action' => 'انشاء',
            'model' => get_class($role),
            'row_id' => $role->id,
            'data_old' => null,
            'data_new' => json_encode($role->getAttributes()),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'company_id' => auth()->user()->company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname . ' بإضافة الدور ' . $role->name . ' في ' . now(),
        ]);
    }

    /**
     * Handle the Role "updated" event.
     */
    public function updated(Role $role): void
    {
        ActivityLog::create([
            'action' => 'تعديل',
            'model' => get_class($role),
            'row_id' => $role->id,
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'data_old' => json_encode($role->getOriginal()),
            'data_new' => json_encode($role),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname . ' بتحديث الدور ' . $role->name . ' في ' . now(),
        ]);
    }

    /**
     * Handle the Role "deleted" event.
     */
    public function deleted(Role $role): void
    {
        ActivityLog::create([
            'action' => 'حذف',
            'model' => get_class($role),
            'row_id' => $role->id,
            'company_id' => auth()->user()->company_id,
            'data_old' => json_encode($role),
            'data_new' => null,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname . ' بحذف الدور ' . $role->name . ' في ' . now(),
        ]);
    }

    /**
     * Handle the Role "restored" event.
     */
    public function restored(Role $role): void
    {
        ActivityLog::create([
            'action' => 'استعادة',
            'model' => get_class($role),
            'row_id' => $role->id,
            'company_id' => auth()->user()->company_id,
            'data_old' => null,
            'data_new' => json_encode($role),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname . ' بإستعادة الدور ' . $role->name . ' في ' . now(),
        ]);
    }

    /**
     * Handle the Role "force deleted" event.
     */
    public function forceDeleted(Role $role): void
    {
        ActivityLog::create([
            'action' => 'حذف نهائي',
            'model' => get_class($role),
            'row_id' => $role->id,
            'company_id' => auth()->user()->company_id,
            'data_old' => json_encode($role),
            'data_new' => null,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname . ' بحذف الدور بشكل نهائي ' . $role->name . ' في ' . now(),
        ]);
    }
}

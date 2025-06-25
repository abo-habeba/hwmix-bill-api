<?php

namespace App\Observers;

use App\Models\ActivityLog;
// use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class RoleObserver
{
    /**
     * Handle the Role "created" event.
     */
    public function created(Role $role): void
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'انشاء',
            'model' => get_class($role),
            'row_id' => $role->id,
            'data_old' => null,
            'data_new' => json_encode($role->getAttributes()),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => $user->company_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . Auth::user()->nickname . ' بإضافة الدور ' . $role->name . ' في ' . now(),
        ]);
    }

    /**
     * Handle the Role "updated" event.
     */
    public function updated(Role $role): void
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'تعديل',
            'model' => get_class($role),
            'row_id' => $role->id,
            'company_id' => Auth::user()->company_id,
            'created_by' => $user->id,
            'data_old' => json_encode($role->getOriginal()),
            'data_new' => json_encode($role),
            'user_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . Auth::user()->nickname . ' بتحديث الدور ' . $role->name . ' في ' . now(),
        ]);
    }

    /**
     * Handle the Role "deleted" event.
     */
    public function deleted(Role $role): void
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'حذف',
            'model' => get_class($role),
            'row_id' => $role->id,
            'company_id' => Auth::user()->company_id,
            'data_old' => json_encode($role),
            'data_new' => null,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . Auth::user()->nickname . ' بحذف الدور ' . $role->name . ' في ' . now(),
        ]);
    }

    /**
     * Handle the Role "restored" event.
     */
    public function restored(Role $role): void
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'استعادة',
            'model' => get_class($role),
            'row_id' => $role->id,
            'company_id' => Auth::user()->company_id,
            'data_old' => null,
            'data_new' => json_encode($role),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . Auth::user()->nickname . ' بإستعادة الدور ' . $role->name . ' في ' . now(),
        ]);
    }

    /**
     * Handle the Role "force deleted" event.
     */
    public function forceDeleted(Role $role): void
    {
        $user = Auth::user();
        ActivityLog::create([
            'action' => 'حذف نهائي',
            'model' => get_class($role),
            'row_id' => $role->id,
            'company_id' => Auth::user()->company_id,
            'data_old' => json_encode($role),
            'data_new' => null,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . Auth::user()->nickname . ' بحذف الدور بشكل نهائي ' . $role->name . ' في ' . now(),
        ]);
    }
}

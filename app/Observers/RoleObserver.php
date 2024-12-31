<?php

namespace App\Observers;

use App\Models\Log;
use App\Models\Role;
use Illuminate\Support\Facades\Log as LogFacade;

class RoleObserver
{
    /**
     * Handle the Role "created" event.
     */
    public function created(Role $role): void
    {
        LogFacade::info('Role Created: ' . $role->name);
        Log::create([
            'action' => 'created',
            'model' => get_class($role),
            'data_old' => null,
            'data_new' => json_encode($role),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
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
        Log::create([
            'action' => 'updated',
            'model' => get_class($role),
            'data_old' => json_encode($role->getOriginal()),
            'data_new' => json_encode($role),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
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
        Log::create([
            'action' => 'deleted',
            'model' => get_class($role),
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
        Log::create([
            'action' => 'restored',
            'model' => get_class($role),
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
        Log::create([
            'action' => 'force_deleted',
            'model' => get_class($role),
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

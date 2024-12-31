<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Log as LogFacade;
use Jenssegers\Agent\Agent;

class UserObserver
{
    private $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }
    public function created(User $user): void
    {
        ActivityLog::create([
            'action' => 'created',
            'model' => get_class($user),
            'data_old' => null,
            'data_new' => json_encode($user),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' ' .
                    $this->agent->version($this->agent->browser()) .
                    ' (' . $this->agent->platform() .
                    ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                ' بإنشاء حساب جديد باسم ' . $user->nickname ,
        ]);
    }

    public function updated(User $user): void
    {
        LogFacade::info('User Updated: ' . $user->nickname);
        ActivityLog::create([
            'action' => 'updated',
            'model' => get_class($user),
            'data_old' => json_encode($user->getOriginal()),
            'data_new' => json_encode($user),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' ' .
                    $this->agent->version($this->agent->browser()) .
                    ' (' . $this->agent->platform() .
                    ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                ' بتحديث بيانات المستخدم ' . $user->nickname .
                ' (البريد الإلكتروني: ' . $user->email . ') ' .
                'في تاريخ ' . now()->format('Y-m-d H:i:s') .
                '. تم تعديل البيانات بنجاح.',
        ]);
    }

    public function deleted(User $user): void
    {
        LogFacade::info('User Deleted: ' . $user->nickname);
        ActivityLog::create([
            'action' => 'deleted',
            'model' => get_class($user),
            'data_old' => json_encode($user),
            'data_new' => null,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' ' .
                    $this->agent->version($this->agent->browser()) .
                    ' (' . $this->agent->platform() .
                    ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                ' بحذف الحساب الخاص بالمستخدم ' . $user->nickname .
                ' بالبريد الإلكتروني ' . $user->email .
                ' في تاريخ ' . now()->format('Y-m-d H:i:s') .
                ' من العنوان IP ' . request()->ip() . '.',
        ]);
    }

    public function restored(User $user): void
    {
        LogFacade::info('User Restored: ' . $user->nickname);
        ActivityLog::create([
            'action' => 'restored',
            'model' => get_class($user),
            'data_old' => null,
            'data_new' => json_encode($user),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' ' .
                    $this->agent->version($this->agent->browser()) .
                    ' (' . $this->agent->platform() .
                    ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                ' باستعادة حساب المستخدم ' . $user->nickname .
                ' (البريد الإلكتروني: ' . $user->email . ') ' .
                'في تاريخ ' . now()->format('Y-m-d H:i:s') . '.',
        ]);
    }

    public function forceDeleted(User $user): void
    {
        LogFacade::info('User Force Deleted: ' . $user->nickname);
        ActivityLog::create([
            'action' => 'force_deleted',
            'model' => get_class($user),
            'data_old' => json_encode($user),
            'data_new' => null,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' ' .
                    $this->agent->version($this->agent->browser()) .
                    ' (' . $this->agent->platform() .
                    ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                ' بحذف حساب المستخدم ' . $user->nickname .
                ' (البريد الإلكتروني: ' . $user->email . ') ' .
                'بشكل نهائي في تاريخ ' . now()->format('Y-m-d H:i:s') .
                '. هذه العملية تمت من العنوان IP ' . request()->ip() . '.',
        ]);
    }
}

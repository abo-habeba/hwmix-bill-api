<?php

namespace App\Traits;

use App\Models\ActivityLog;


trait LogsActivity
{
    public function logCreated($text)
    {
        ActivityLog::create([
            'action' => 'انشاء',
            'model' => get_class($this),
            'data_old' => null,
            'data_new' => json_encode($this->getAttributes()),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => ' قام المستخدم  ' . auth()->user()->nickname .
                $text,
        ]);
    }

    public function logUpdated($text)
    {
        ActivityLog::create([
            'action' => 'تعديل',
            'model' => get_class($this),
            'data_old' => json_encode($this->getOriginal()),
            'data_new' => json_encode($this->getChanges()),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                ' بتعديل ' . $text,
        ]);
    }

    public function logDeleted($text)
    {
        ActivityLog::create([
            'action' => 'حذف',
            'model' => get_class($this),
            'data_old' => json_encode($this->getAttributes()),
            'data_new' => null,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                ' بحذف ' . $text,
        ]);
    }

    public function logRestored($text)
    {
        ActivityLog::create([
            'action' => 'استعادة',
            'model' => get_class($this),
            'data_old' => null,
            'data_new' => json_encode($this->getAttributes()),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                ' باستعادة ' . $text,
        ]);
    }

    public function logForceDeleted($text)
    {
        ActivityLog::create([
            'action' => 'حذف نهائي',
            'model' => get_class($this),
            'data_old' => json_encode($this->getAttributes()),
            'data_new' => null,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                '  بحذف  ' . $text . ' حذف نهائي ',
        ]);
    }
}

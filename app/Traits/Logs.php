<?php

namespace App\Traits;

use App\Models\Log;
use Jenssegers\Agent\Agent;

trait Logs
{
    private $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }

    public function logCreated($text)
    {
        Log::create([
            'action' => 'انشاء',
            'model' => get_class($this),
            'data_old' => null,
            'data_new' => json_encode($this->getAttributes()),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' ' .
                $this->agent->version($this->agent->browser()) .
                ' ( ' . $this->agent->platform() .
                ' ' . $this->agent->version($this->agent->platform()) . ' ) ',
            'url' => request()->getRequestUri(),
            'description' => ' قام المستخدم  ' . auth()->user()->nickname .
                $text,
        ]);
    }

    public function logUpdated($text)
    {
        Log::create([
            'action' => 'تعديل',
            'model' => get_class($this),
            'data_old' => json_encode($this->getOriginal()),
            'data_new' => json_encode($this->getChanges()),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' ' .
                $this->agent->version($this->agent->browser()) .
                ' (' . $this->agent->platform() .
                ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                ' بتعديل ' . $text,
        ]);
    }

    public function logDeleted($text)
    {
        Log::create([
            'action' => 'حذف',
            'model' => get_class($this),
            'data_old' => json_encode($this->getAttributes()),
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
                ' بحذف ' . $text,
        ]);
    }

    public function logRestored($text)
    {
        Log::create([
            'action' => 'استعادة',
            'model' => get_class($this),
            'data_old' => null,
            'data_new' => json_encode($this->getAttributes()),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $this->agent->browser() . ' ' .
                $this->agent->version($this->agent->browser()) .
                ' (' . $this->agent->platform() .
                ' ' . $this->agent->version($this->agent->platform()) . ')',
            'url' => request()->getRequestUri(),
            'description' => 'قام المستخدم ' . auth()->user()->nickname .
                ' باستعادة ' . $text,
        ]);
    }

    public function logForceDeleted($text)
    {
        Log::create([
            'action' => 'حذف نهائي',
            'model' => get_class($this),
            'data_old' => json_encode($this->getAttributes()),
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
                '  بحذف  ' . $text . ' حذف نهائي ',
        ]);
    }
}

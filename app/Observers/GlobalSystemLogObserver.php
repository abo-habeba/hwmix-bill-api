<?php
namespace App\Observers;

use App\Models\Log; // نموذج الـ Log الذي قمت بإنشائه
use Illuminate\Database\Eloquent\Model;


class GlobalSystemLogObserver
{
    public function created(Model $model)
    {
        // Log::info('تم إنشاء سجل جديد في جدول ' . $model->getTable());
        // عند إنشاء مستخدم جديد
        Log::create([
            'action' => 'created',
            'model' => get_class($model),
            'data_old' => null,
            'data_new' => json_encode($model),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
        ]);
    }

    public function updated(Model $model)
    {
        // عند تحديث المستخدم
        Log::create([
            'action' => 'updated',
            'model' => get_class($model),
            'data_old' => json_encode($model->getOriginal()),
            'data_new' => json_encode($model),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
        ]);
    }

    public function deleted(Model $model)
    {
        // عند حذف المستخدم
        Log::create([
            'action' => 'deleted',
            'model' => get_class($model),
            'data_old' => json_encode($model),
            'data_new' => null,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
        ]);
    }
}

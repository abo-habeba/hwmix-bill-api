<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait Blameable
{
    public static function bootBlameable()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                // تعيين created_by إذا كان فارغًا
                if (empty(data_get($model, 'created_by'))) {
                    $model->created_by = Auth::id();
                }

                // تعيين company_id إذا كان فارغًا ومتاحًا في بيانات المستخدم الحالي
                if (empty(data_get($model, 'company_id')) && Auth::user()->company_id) {
                    $model->company_id = Auth::user()->company_id;
                }
            }
        });
    }
}

<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait Blameable
{
    public static function bootBlameable()
    {
        static::creating(function ($model) {
            if (!Auth::check())
                return;

            // إضافة created_by إذا كان العمود موجود
            if (
                Schema::hasColumn($model->getTable(), 'created_by') &&
                blank(data_get($model, 'created_by'))
            ) {
                $model->created_by = Auth::id();
            }

            // إضافة company_id إذا كان العمود موجود
            if (
                Schema::hasColumn($model->getTable(), 'company_id') &&
                blank(data_get($model, 'company_id'))
            ) {
                $model->company_id = optional(Auth::user())->company_id;
            }
        });
    }
}

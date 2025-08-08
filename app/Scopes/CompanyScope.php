<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    /**
     * تطبيق النطاق على استعلام الباني.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        // لا تطبق النطاق إذا لم يكن هناك مستخدم مسجل الدخول
        // أو إذا كان المستخدم لديه صلاحية 'admin.super' (المدير العام يرى كل شيء)
        if (Auth::check() && !Auth::user()->hasPermissionTo(perm_key('admin.super'))) {
            $builder->where('company_id', Auth::user()->company_id);
        }
    }
}

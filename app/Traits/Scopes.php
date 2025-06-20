<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Scopes
{
    // التابعين لنفس الشركة
    public function scopeCompany(Builder $query)
    {
        $user = auth()->user();
        if (!$user)
            return $query;

        // إذا كان حقل معرف الشركه في الجدول فارغ  يتم تطبيق منطق النطاق
        $query->where(function ($q) use ($user) {
            $q
                ->where(function ($subQ) {
                    $subQ->whereNull('company_id')->orWhere('company_id', '');
                })
                ->orWhere(function ($subQ) use ($user) {
                    $subQ->where('company_id', $user->company_id);
                });
        });

        // إذا كان السجل معرف الشركه فارغ  نطبق منطق له
        $query->orWhere(function ($q) use ($user) {
            $q->whereNull('company_id')->orWhere('company_id', '');
            $subUsers = \App\Models\User::where('created_by', $user->id)->pluck('id');
            $q->whereIn('created_by', $subUsers->push($user->id));
        });

        return $query;
    }

    // التابعين للمستخدمين التابعين لنفس المستخدم
    public function scopeOwn(Builder $query)
    {
        $user = auth()->user();

        if ($user) {
            $subUsers = \App\Models\User::where('created_by', $user->id)->pluck('id');
            $query->whereIn('created_by', $subUsers->push($user->id));
        }
    }

    // التابعين لنفس المستخدم فقط
    public function scopeSelf(Builder $query)
    {
        $user = auth()->user();

        if ($user) {
            $query->where('created_by', $user->id);
        }
    }
}

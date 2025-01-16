<?php

namespace App\Traits;
use Illuminate\Database\Eloquent\Builder;
trait Scopes
{

    // التابعين لنفس الشركة
    public function scopeCompany(Builder $query)
    {
        $user = auth()->user();
        $query->where('company_id', $user->company_id);
        return $query;
    }
    //  جلب السجلات التي أنشأها المستخدم أو المستخدمين التابعين له
    public function scopeOwn(Builder $query)
    {
        $user = auth()->user();

        if ($user) {
            $subUsers = \App\Models\User::where('created_by', $user->id)->pluck('id');
            $query->whereIn('created_by', $subUsers->push($user->id));
        }
    }
    // جلب السجلات اللتي هنشاها المستخدم
    public function scopeSelf(Builder $query)
    {
        $user = auth()->user();

        if ($user) {
            $query->where('created_by', $user->id);
        }
    }
}

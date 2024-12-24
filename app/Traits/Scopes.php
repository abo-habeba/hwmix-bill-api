<?php

namespace App\Traits;
use Illuminate\Database\Eloquent\Builder;
trait Scopes
{
    //  جلب السجلات التي أنشأها المستخدم أو المستخدمين التابعين له
    public function scopeOwn(Builder $query)
    {
        $user = auth()->user();

        if ($user) {
            $subUsers = \App\Models\User::where('created_by', $user->id)->pluck('id');
            $query->whereIn('created_by', $subUsers->push($user->id));
        }
    }
    // سكوب لجلب السجلات التي أنشأها المستخدمون الذين ينتمون لنفس الشركه
    public function scopeCompanyOwn(Builder $query)
    {
        $user = auth()->user();

        if ($user && $user->company_id) {
            $usersInSameCompany = \App\Models\User::where('company_id', $user->company_id)
                ->pluck('id');

            $query->whereIn('created_by', $usersInSameCompany);
        }
        return $query;
    }
}

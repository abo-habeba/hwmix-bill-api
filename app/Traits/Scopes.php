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
    public function scopeOwn(Builder $query)
    {
        $user = auth()->user();

        if ($user) {
            $subUsers = \App\Models\User::where('created_by', $user->id)->pluck('id');
            $query->whereIn('created_by', $subUsers->push($user->id));
        }
    }
    public function scopeSelf(Builder $query)
    {
        $user = auth()->user();

        if ($user) {
            $query->where('created_by', $user->id);
        }
    }

    /**
     * Scope a query to only include records created by the current user or their sub-users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function scopeCreatedBySubUsers(Builder $query)
    {
        $user = auth()->user();

        if ($user) {
            $subUsers = \App\Models\User::where('created_by', $user->id)->pluck('id');
            $query->whereIn('created_by', $subUsers->push($user->id));
        }
    }

    /**
     * Scope a query to only include records created by the current user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function scopeCreatedByUser(Builder $query)
    {
        $user = auth()->user();

        if ($user) {
            $query->where('created_by', $user->id);
        }
    }

}

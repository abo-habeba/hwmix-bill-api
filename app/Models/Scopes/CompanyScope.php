<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $authUser = Auth::user();

        if ($authUser) {
            $companyId = $authUser->company_id;

            if ($companyId !== null) {
                $builder->where('company_id', $companyId);
            }
        }
    }
}

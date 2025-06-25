<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait RolePermissions
{
    public function isCompany()
    {
        return $this->creator->company_id == Auth::user()->company_id;
    }

    // التحقق مما اذا كان المستخدم المسجل انشا المستخدم اللذي انشا النموزج
    public function isOwn()
    {
        return $this->creator->created_by == auth()->id();
    }

    public function isٍٍٍSelf()
    {
        return $this->created_by == auth()->id();
    }
}

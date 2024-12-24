<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait RolePermissions
{
// التحقق مما اذا كان المستخدم المسجل انشا المستخدم اللذي انشا النموزج
    public function isOwn()
    {
        return optional($this->creator)->created_by == auth()->id();
    }
}

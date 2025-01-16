<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Contracts\Role as RoleContract;

class Role extends SpatieRole implements RoleContract
{
    use HasRoles, RolePermissions, Scopes, LogsActivity,HasPermissions;
    protected $fillable = [
        'name',
        'guard_name',
        'created_by',
        'company_id',
    ];
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}

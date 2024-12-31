<?php

namespace App\Models;

use App\Traits\LogsActivity;
use App\Traits\Scopes;
use App\Traits\RolePermissions;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Contracts\Role as RoleContract;
class Role extends SpatieRole implements RoleContract
{
    use HasRoles, RolePermissions, Scopes, LogsActivity;
    protected $fillable = [
        'name',
        'guard_name',
        'created_by',
    ];
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}

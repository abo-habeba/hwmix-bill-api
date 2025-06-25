<?php

namespace App\Models;

use App\Traits\Translations\Translatable;
use App\Traits\Blameable;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @mixin IdeHelperActivityLog
 */
class ActivityLog extends Model
{
    use HasFactory, Notifiable, Translatable, HasRoles, HasApiTokens, Filterable, Scopes, RolePermissions, LogsActivity, Blameable;

    protected $fillable = [
        'action',
        'model',
        'row_id',
        'data_old',
        'data_new',
        'description',
        'user_id',
        'created_by',
        'company_id',
        'user_agent',
        'ip_address',
        'url',
    ];

    protected $casts = [
        'data_old' => 'array',
        'data_new' => 'array',
    ];
}

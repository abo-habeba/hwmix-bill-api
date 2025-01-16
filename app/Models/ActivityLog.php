<?php

namespace App\Models;

use App\Models\Scopes\LatestScope;
use App\Traits\Scopes;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Scopes\CompanyScope;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Traits\Translations\Translatable;
// use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// #[ScopedBy([CompanyScope::class])]
class ActivityLog extends Model
{
    use HasFactory, Notifiable, Translatable, HasRoles, HasApiTokens, Filterable, Scopes, RolePermissions, LogsActivity;

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

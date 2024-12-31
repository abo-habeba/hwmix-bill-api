<?php

namespace App\Models;

use App\Traits\RolePermissions;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Log extends Model
{
    use HasFactory, Scopes, RolePermissions;

    protected $fillable = [
        'action',
        'model',
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

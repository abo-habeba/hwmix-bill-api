<?php

namespace App\Models;

use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoleCompany extends Model
{
    use HasFactory, Scopes;

    protected $table = 'role_company';
    protected $fillable = ['role_id', 'company_id', 'created_by'];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function permissions()
    {
        return $this->hasManyThrough(Permission::class, Role::class);
    }
}


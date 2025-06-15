<?php

namespace App\Models;

use App\Models\User;
use App\Traits\Scopes;
use App\Models\Company;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\Blameable;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
#[ScopedBy([CompanyScope::class])]
class CashBox extends Model
{
    use Scopes, LogsActivity, RolePermissions, Blameable;
    protected $fillable = [
        'name',
        'balance',
        'cash_type',
        'is_default',
        'cash_box_type_id',
        'user_id',
        'created_by',
        'company_id',
    ];
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function typeBox(): BelongsTo
    {
        return $this->belongsTo(CashBoxType::class, 'cash_box_type_id');
    }
    // العلاقة مع المستخدم
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // العلاقة مع الشركات
    public function company(): belongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

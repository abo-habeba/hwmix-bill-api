<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
#[ScopedBy([CompanyScope::class])]
class CashBox extends Model
{
    protected $fillable = [
        'name',
        'balance',
        'cash_type',
        'user_id',
        'created_by',
        'company_id',
    ];
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    // العلاقة مع المستخدمين
    public function user(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_company_cash')
            ->withPivot('company_id') // إذا كنت بحاجة إلى الوصول إلى company_id
            ->withTimestamps(); // إذا كنت بحاجة إلى الوصول إلى timestamps
    }

    // العلاقة مع الشركات
    public function company(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_company_cash')
            ->withPivot('user_id') // إذا كنت بحاجة إلى الوصول إلى user_id
            ->withTimestamps(); // إذا كنت بحاجة إلى الوصول إلى timestamps
    }
}

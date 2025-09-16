<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Traits\Translations\Translatable;
use App\Traits\Filterable;
use App\Traits\HasImages;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// #[ScopedBy([CompanyScope::class])]


class Company extends Model
{
    use HasFactory, Notifiable, Translatable, HasRoles, Filterable, Scopes, RolePermissions, LogsActivity, HasImages;

    protected $fillable = [
        'name',
        'description',
        'field',
        'owner_name',
        'address',
        'phone',
        'email',
        'created_by',
        'company_id',
    ];

    // Define the many-to-many relationship

    // يجيب المستخدمين مباشرة (Many To Many)
    public function users(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class, 'company_user', 'company_id', 'user_id')
            ->withTimestamps()
            ->withPivot([
                'nickname_in_company',
                'full_name_in_company',
                'position_in_company',
                'balance_in_company',
                'customer_type_in_company',
                'status',
                'user_phone',
                'user_email',
                'user_username',
                'created_by'
            ]);
    }

    // يجيب العضويات (Pivot Model)
    public function companyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'company_id');
    }



    public function userCompanyCash()
    {
        return $this
            ->belongsToMany(User::class, 'user_company_cash')
            ->withPivot('cash_box_id', 'created_by');  // أضف الحقول الإضافية التي تريد الوصول إليها
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // العلاقة مع صناديق النقدية
    public function cashBoxes(): BelongsToMany
    {
        return $this
            ->belongsToMany(CashBox::class, 'user_company_cash')
            ->withPivot('user_id')  // إذا كنت بحاجة إلى الوصول إلى user_id
            ->withTimestamps();  // إذا كنت بحاجة إلى الوصول إلى timestamps
    }

    public function logo()
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', 'logo');
    }
}

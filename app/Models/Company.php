<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Traits\Translations\Translatable;
use App\Traits\Filterable;
use App\Traits\HandlesImages;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

// #[ScopedBy([CompanyScope::class])]
class Company extends Model
{
    use HasFactory, Notifiable, Translatable, HasRoles, Filterable, Scopes, RolePermissions, LogsActivity, HandlesImages;

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
    public function users()
    {
        return $this->belongsToMany(User::class, 'company_user');
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

    // نطاق رؤية الشركات للمستخدم
    public function scopeVisibleFor($query, \App\Models\User $user)
    {
        if (!$user) {
            return $query->whereRaw('0 = 1');  // لا يرجّع أي بيانات
        }

        // الحالة 1: صلاحية مشاهدة جميع الشركات
        if ($user->hasAnyPermission(['super_admin'])) {
            return $query;
        }
        // الحالة 2: صلاحية مشاهدة الشركات التابعة له أو للمستخدمين اللي أنشأهم
        if ($user->hasAnyPermission('companys_all_own', 'companys_all', 'company_owner')) {
            $subUsers = \App\Models\User::where('created_by', $user->id)->pluck('id');
            return $query->whereIn('created_by', $subUsers->push($user->id));
        }

        // الحالة 3: صلاحية مشاهدة الشركة المرتبط بها فقط
        if ($user->hasAnyPermission('companys_all_self')) {
            return $query->where('id', $user->company_id);
        }

        // الحالة 4: لا يملك أي صلاحية لكن عنده علاقة many-to-many مع شركات
        if (method_exists($user, 'companies') && $user->companies()->exists()) {
            return $query->whereIn('id', $user->companies->pluck('id'));
        }

        // الحالة 5: لا يوجد صلاحية ولا علاقة → لا يرى أي شركة
        return $query->whereRaw('0 = 1');
    }
}

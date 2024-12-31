<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\Logs;
use App\Traits\RolePermissions;
use App\Traits\Scopes;
use Exception;
use App\Traits\Filterable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Traits\Translations\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Translatable, HasRoles, HasApiTokens, Filterable, Scopes, RolePermissions, Logs;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nickname',
        'full_name',
        'username',
        'email',
        'password',
        'phone',
        'position',
        'settings',
        'last_login_at',
        'email_verified_at',
        'company_id',
        'created_by',
        'balance',
        'status',
    ];

    // علاقة Polymorphic مع جدول الترجمة
    public function trans()
    {
        return $this->morphMany(Translation::class, 'model');
    }
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getRolesWithPermissions()
    {
        // جلب الأدوار مع الصلاحيات
        return $this->roles()->with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'created_by' => $role->created_by,
                'company_id' => $role->company_id,
                'permissions' => $role->permissions->pluck('name'), // جلب أسماء الصلاحيات فقط
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });
    }


    // دالة لإرجاع الصلاحيات
    public function getPermissions()
    {
        return $this->permissions()->pluck('name');
    }

    // المعاملات التي قام بها المستخدم
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    // المستخدمين الذين أنشأهم هذا المستخدم
    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    // تحويل
    public function transferTo(User $targetUser, $amount)
    {
        DB::transaction(function () use ($targetUser, $amount) {
            if ($this->balance < $amount) {
                throw new Exception('Insufficient balance.');
            }

            // خصم من الرصيد الحالي
            $this->balance -= $amount;
            $this->save();

            // إضافة إلى رصيد المستخدم المستهدف
            $targetUser->balance += $amount;
            $targetUser->save();

            // تسجيل العملية
            Transaction::create([
                'user_id' => $this->id,
                'target_user_id' => $targetUser->id,
                'type' => 'transfer',
                'amount' => $amount,
                'description' => "Transfer to user ID {$targetUser->id}",
            ]);
        });
    }


    // إيداع الرصيد
    public function deposit($amount)
    {
        DB::transaction(function () use ($amount) {
            $this->balance += $amount;
            $this->save();

            // تسجيل العملية
            Transaction::create([
                'user_id' => $this->id,
                'type' => 'deposit',
                'amount' => $amount,
                'description' => 'Deposit to account',
            ]);
        });
    }

    public function withdraw($amount)
    {
        DB::transaction(function () use ($amount) {
            if ($this->balance < $amount) {
                throw new Exception('Insufficient balance.');
            }

            $this->balance -= $amount;
            $this->save();

            // تسجيل العملية
            Transaction::create([
                'user_id' => $this->id,
                'type' => 'withdraw',
                'amount' => $amount,
                'description' => 'Withdraw from account',
            ]);
        });
    }

}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Exception;
// use App\Models\Role;
use App\Traits\Scopes;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Traits\Translations\Translatable;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Translatable, HasRoles, HasApiTokens, Filterable, Scopes, RolePermissions, LogsActivity, HasPermissions;

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

    // Define the many-to-many relationship
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_user', 'user_id', 'company_id');
    }
    public function cashBoxes(): BelongsToMany
    {
        return $this->belongsToMany(CashBox::class, 'user_company_cash')
                    ->withPivot('company_id') // إذا كنت بحاجة إلى الوصول إلى company_id
                    ->withTimestamps(); // إذا كنت بحاجة إلى الوصول إلى timestamps
    }
    public function createdRoles()
    {
        return $this->hasManyThrough(
            Role::class,
            RoleCompany::class,
            'created_by', // المفتاح الخارجي في جدول RoleCompany يشير إلى المستخدم
            'id',         // المفتاح الخارجي في جدول Role يشير إلى RoleCompany
            'id',         // المفتاح الأساسي للمستخدم
            'role_id'     // المفتاح في جدول RoleCompany يشير إلى جدول Role
        );
    }
    public function getRolesWithPermissions()
    {
        return $this->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                    ];
                }),
            ];
        });
    }
    // المعاملات التي قام بها المستخدم
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

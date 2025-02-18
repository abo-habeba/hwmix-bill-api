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
    public function trans()
    {
        return $this->morphMany(Translation::class, 'model');
    }
    // Define the many-to-many relationship
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_user', 'user_id', 'company_id');
    }
    public function balanceBox($id = null)
    {
        $cashBoxes = $this->cashBoxes;
        $cashBox = $id
            ? $cashBoxes->firstWhere('id', $id)
            : $cashBoxes->firstWhere('is_default', true);
        return $cashBox ? $cashBox->balance : 0;
    }
    public function cashBoxes()
    {
        return $this->hasMany(CashBox::class);
    }
    public function cashBoxeDefault()
    {
        return $this->hasOne(CashBox::class)->where('is_default', 1);
    }
    public function cashBoxesByCompany()
    {
        return $this->cashBoxes()->where('company_id', $this->company_id)->get();
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

    public function deposit($amount, $cashBoxId = null)
    {
        DB::beginTransaction();
        try {
            $cashBoxes = $this->cashBoxes;

            $cashBox = $cashBoxId
                ? $cashBoxes->firstWhere('id', $cashBoxId)
                : $cashBoxes->firstWhere('is_default', true);

            if (!$cashBox) {
                throw new Exception('Cash box not found.');
            }

            $cashBox->increment('balance', $amount);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function withdraw($amount, $cashBoxId = null)
    {
        DB::beginTransaction();
        try {
            $cashBoxes = $this->cashBoxes;

            $cashBox = $cashBoxId
                ? $cashBoxes->firstWhere('id', $cashBoxId)
                : $cashBoxes->firstWhere('is_default', true);

            if (!$cashBox) {
                throw new Exception('Cash box not found.');
            }

            if ($cashBox->balance < $amount) {
                throw new Exception('Insufficient funds in the cash box.');
            }

            $cashBox->decrement('balance', $amount);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    // تحويل مبلغ بين المستخدمين
    public function transfer($cashBoxId, $targetUserId, $amount, $description = null)
    {
        if (!$this->hasAnyPermission(['super_admin', 'transfer', 'company_owner'])) {
            throw new Exception('Unauthorized: You do not have permission to transfer.');
        }

        DB::beginTransaction();

        try {
            $cashBox = CashBox::where('id', $cashBoxId)
                ->where('user_id', $this->id)
                ->where('company_id', $this->company_id)
                ->firstOrFail();

            if ($cashBox->balance < $amount) {
                throw new Exception('Insufficient funds in the cash box.');
            }

            $targetUser = User::findOrFail($targetUserId);
            $targetCashBox = CashBox::where('user_id', $targetUser->id)
                ->where('cash_type', $cashBox->cash_type)
                ->where('company_id', $this->company_id)
                ->first();

            if (!$targetCashBox) {
                throw new Exception('Target user does not have a matching cash box.');
            }

            $cashBox->decrement('balance', $amount);
            $targetCashBox->increment('balance', $amount);

            $transaction = Transaction::create([
                'user_id' => $this->id,
                'target_user_id' => $targetUserId,
                'type' => 'transfer',
                'amount' => $amount,
                'description' => $description,
                'company_id' => $cashBox->company_id,
                'created_by' => $this->id,
            ]);

            Transaction::create([
                'user_id' => $targetUserId,
                'original_transaction_id' => $transaction->id,
                'type' => 'deposit',
                'amount' => $amount,
                'description' => 'Received from transfer',
                'company_id' => $targetCashBox->company_id,
                'created_by' => $this->id,
            ]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


}

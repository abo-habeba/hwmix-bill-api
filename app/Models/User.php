<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Exception;
// use App\Models\Role;
use App\Traits\Scopes;
use App\Traits\HasImages;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use App\Services\CashBoxService;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Traits\Translations\Translatable;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @method void deposit(float|int $amount)
 * @mixin IdeHelperUser
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Translatable, HasRoles, HasApiTokens, Filterable, Scopes, RolePermissions, LogsActivity, HasPermissions, HasImages;

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
        'customer_type',
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
    protected static function booted(): void
    {
        static::created(function (User $user) {
            app(\App\Services\CashBoxService::class)->ensureCashBoxForUser($user);
        });
    }

    public function trans()
    {
        return $this->morphMany(Translation::class, 'model');
    }

    public function companies()
    {
        return $this
            ->belongsToMany(Company::class, 'company_user', 'user_id', 'company_id')
            ->withTimestamps()
            ->withPivot('created_by');
    }

    public function companyUsersCash()
    {
        return $this
            ->belongsToMany(Company::class, 'user_company_cash', 'user_id', 'company_id')
            ->withPivot('cash_box_id', 'created_by');  // أضف الحقول الإضافية التي تريد الوصول إليها
    }

    /**
     * Get the balance of the default cash box for the active company.
     */
    public function balanceBox($id = null)
    {
        $query = $this->cashBoxes();
        $cashBox = null;
        if ($id) {
            $cashBox = $query->where('id', $id)->where('company_id', $this->company_id)->first();
        } else {
            $cashBox = $query->where('company_id', $this->company_id)->where('is_default', true)->first();
        }
        if (!$cashBox) {
            $this->refresh();
            $query = $this->cashBoxes();
            if ($id) {
                $cashBox = $query->where('id', $id)->where('company_id', $this->company_id)->first();
            } else {
                $cashBox = $query->where('company_id', $this->company_id)->where('is_default', true)->first();
            }
        }
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
            'created_by',  // المفتاح الخارجي في جدول RoleCompany يشير إلى المستخدم
            'id',  // المفتاح الخارجي في جدول Role يشير إلى RoleCompany
            'id',  // المفتاح الأساسي للمستخدم
            'role_id'  // المفتاح في جدول RoleCompany يشير إلى جدول Role
        );
    }

    // كل الأقساط اللي تخص العميل ده
    public function installments()
    {
        return $this->hasMany(Installment::class, 'user_id');
    }

    // كل الأقساط اللي الموظف ده أضافها
    public function createdInstallments()
    {
        return $this->hasMany(Installment::class, 'created_by');
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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }


    public function withdraw(float $amount, $cashBoxId = null)
    {
        $amount = floatval($amount);

        DB::beginTransaction();
        try {
            $this->refresh();

            $query = $this->cashBoxes();
            $cashBox = $cashBoxId
                ? $query->where('id', $cashBoxId)->first()
                : $query->where('company_id', $this->company_id)->where('is_default', true)->first();

            // Fallback لو الخزنة مش موجودة
            if (!$cashBox) {
                $this->refresh();
                $cashBox = $this->cashBoxes()
                    ->where('company_id', $this->company_id)
                    ->where('is_default', true)
                    ->first();
            }

            if (!$cashBox) {
                DB::rollBack();
                throw new \Exception("لم يتم العثور على خزنة مناسبة.");
            }

            $cashBox->decrement('balance', $amount);

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deposit(float $amount, $cashBoxId = null)
    {
        $amount = floatval($amount);
        DB::beginTransaction();
        try {
            $this->refresh();

            $query = $this->cashBoxes();
            $cashBox = $cashBoxId
                ? $query->where('id', $cashBoxId)->where('company_id', $this->company_id)->first()
                : $query->where('company_id', $this->company_id)->where('is_default', true)->first();

            // Fallback لو الخزنة مش موجودة
            if (!$cashBox) {
                $this->refresh();
                $cashBox = $this->cashBoxes()
                    ->where('company_id', $this->company_id)
                    ->where('is_default', true)
                    ->first();
            }

            if (!$cashBox) {
                DB::rollBack();
                throw new \Exception("لم يتم العثور على خزنة مناسبة.");
            }

            $cashBox->increment('balance', $amount);

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
    // تحويل مبلغ بين المستخدمين
    public function transfer($cashBoxId, $targetUserId, $amount, $description = null)
    {

        $amount = floatval($amount);
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
                'cashbox_id' => $cashBox->id ?? null,
                'target_user_id' => $targetUserId,
                'target_cashbox_id' => $targetCashBox->id ?? null,
                'created_by' => $this->id,
                'company_id' => $cashBox->company_id ?? null,
                'type' => 'تحويل',
                'amount' => $amount,
                'balance_before' => $cashBox->balance + $amount ?? null,
                'balance_after' => $cashBox->balance ?? null,
                'description' => $description,
                'original_transaction_id' => null,
            ]);

            Transaction::create([
                'user_id' => $targetUserId,
                'cashbox_id' => $targetCashBox->id ?? null,
                'target_user_id' => $this->id,
                'target_cashbox_id' => $cashBox->id ?? null,
                'created_by' => $this->id,
                'company_id' => $targetCashBox->company_id ?? null,
                'type' => 'إيداع',
                'amount' => $amount,
                'balance_before' => $targetCashBox->balance - $amount ?? null,
                'balance_after' => $targetCashBox->balance ?? null,
                'description' => 'Received from transfer',
                'original_transaction_id' => $transaction->id,
            ]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function installmentPlans()
    {
        return $this->hasMany(InstallmentPlan::class);
    }


    /**
     * إرجاع جميع معرفات المستخدمين التابعين (hierarchy) للمستخدم الحالي داخل الشركة النشطة فقط.
     * يشمل جميع المستخدمين الذين أنشأهم هذا المستخدم بشكل متداخل (recursive).
     *
     * @return array
     */
    public function getDescendantUserIds(): array
    {
        $companyId = $this->company_id;
        $descendants = [];
        $stack = [$this->id];
        while (!empty($stack)) {
            $parentId = array_pop($stack);
            // جلب المستخدمين الذين أنشأهم هذا المستخدم داخل نفس الشركة فقط
            $children = self::where('created_by', $parentId)
                ->where('company_id', $companyId)
                ->pluck('id')
                ->toArray();
            foreach ($children as $childId) {
                if (!in_array($childId, $descendants)) {
                    $descendants[] = $childId;
                    $stack[] = $childId;
                }
            }
        }
        // حذف معرف المستخدم الحالي من القائمة (لأنك غالباً تضيفه يدوياً في الاستعلام)
        if (($key = array_search($this->id, $descendants)) !== false) {
            unset($descendants[$key]);
        }
        return array_values($descendants);
    }

    /**
     * Ensure the user has a cash box for every company they belong to.
     * If not, create a default cash box for that company.
     */
    public function ensureCashBoxesForAllCompanies(): void
    {
        // تحديد الشركات التي يجب إنشاء صناديق كاش لها
        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            $companies = Company::all();
        } else {
            // غير كده هات الشركات اللي مرتبط بيها فقط
            $companies = $this->companies;
        }

        // استخراج معرفات الشركات
        $companyIds = $companies->pluck('id')->toArray();

        // استدعاء خدمة CashBoxService لإنشاء الصناديق
        //  إنشاء الصندوق ويستخدم الكود المركزي في الخدمة
        app(CashBoxService::class)->ensureCashBoxesForUserCompanies($this, $companyIds, $this->created_by ?? $this->id);
    }
}

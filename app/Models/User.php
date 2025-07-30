<?php

namespace App\Models;

use Exception;
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
use Illuminate\Support\Facades\Auth;
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
        // 'balance', // تم التعليق عليه - الرصيد يدار في CashBox
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
            ->withPivot('cash_box_id', 'created_by');
    }

    public function cashBoxes()
    {
        return $this->hasMany(CashBox::class, 'user_id');
    }

    /**
     * علاقة المستخدم بالخزنة الافتراضية.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cashBoxeDefault()
    {
        return $this->hasOne(CashBox::class, 'user_id', 'id')
            ->where(function ($query) {
                $query->where('is_default', true);
                // نستخدم قيمة الشركة من هذا الـ user instance
                if (!is_null($this->company_id)) {
                    $query->where('company_id', $this->company_id);
                }
            });
    }

    /**
     * Get the balance of a specific or the default cash box for the active company.
     *
     * @param int|null $id معرف صندوق النقدية.
     * @return float
     */
    public function balanceBox($id = null)
    {
        $cashBox = null;
        if ($id) {
            $cashBox = $this->cashBoxes()->where('id', $id)->where('company_id', $this->company_id)->first();
        } else {
            // استدعاء العلاقة كدالة ثم استخدام first() للحصول على النموذج
            $cashBox = $this->cashBoxeDefault()->first();
        }
        return $cashBox ? $cashBox->balance : 0.0;
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
            'created_by',
            'id',
            'id',
            'role_id'
        );
    }

    public function installments()
    {
        return $this->hasMany(Installment::class, 'user_id');
    }

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

    /**
     * خصم مبلغ من رصيد المستخدم (خزنته).
     *
     * @param float $amount المبلغ المراد سحبه.
     * @param int|null $cashBoxId معرف صندوق النقدية المحدد (اختياري).
     * @return bool True عند النجاح.
     * @throws \Exception عند الفشل (مثل عدم وجود خزنة أو رصيد غير كافٍ).
     */
    public function withdraw(float $amount, $cashBoxId = null): bool
    {
        $amount = floatval($amount);
        $authCompanyId = Auth::user()->company_id ?? null;

        DB::beginTransaction();
        try {
            $cashBox = null;

            if ($cashBoxId) {
                // البحث عن صندوق نقدية محدد بمعرفه وتابع لهذا المستخدم مباشرة من قاعدة البيانات
                $cashBox = CashBox::query()->where('id', $cashBoxId)->where('user_id', $this->id)->first();

                if ($cashBox) {
                    DB::rollBack();
                    throw new \Exception("المستخدم ليس له خزنة.");
                }
            } else {
                // البحث عن الخزنة الافتراضية للمستخدم الحالي ($this) والتي تتبع الشركة النشطة للمستخدم الموثق
                if (is_null($authCompanyId)) {
                    DB::rollBack();
                    throw new \Exception("لا توجد شركة نشطة للمستخدم الحالي لتحديد الخزنة الافتراضية.");
                }
                $cashBox = CashBox::query()->where('user_id', $this->id)->where('is_default', true)->where('company_id', $authCompanyId)->first();
                if ($cashBox) {
                    DB::rollBack();
                    throw new \Exception("المستخدم ليس له خزنة.");
                }
            }

            if (!$cashBox) {
                DB::rollBack();
                throw new \Exception("لم يتم العثور على خزنة مناسبة للمستخدم : {$this->nickname}");
            }


            $cashBox->decrement('balance', $amount);
            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('User Model Withdraw: فشل السحب.', [
                'error' => $e->getMessage(),
                'user_id' => $this->id,
                'amount' => $amount,
                'cash_box_id' => $cashBoxId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * إيداع مبلغ في رصيد المستخدم (خزنته).
     *
     * @param float $amount المبلغ المراد إيداعه.
     * @param int|null $cashBoxId معرف صندوق النقدية المحدد (اختياري).
     * @return bool True عند النجاح.
     * @throws \Exception عند الفشل (مثل عدم وجود خزنة).
     */

    public function deposit(float $amount, $cashBoxId = null): bool
    {
        $amount = floatval($amount);
        DB::beginTransaction();
        $authUser = Auth::user();
        $authCompanyId = $authUser->company_id ?? null;
        try {
            $cashBox = null;
            Log::info('Deposit debug info:', [
                'cashBoxId' => $cashBoxId,
                'this_id' => $this->id,
                'authUser_id' => $authUser->id,
                'cashBoxRecordExists' => CashBox::where('id', $cashBoxId)->exists(),
                'cashBoxUserId' => CashBox::where('id', $cashBoxId)->value('user_id'),
            ]);
            if ($cashBoxId) {
                $cashBox = CashBox::query()->where('id', $cashBoxId)->where('user_id', $this->id)->first();
                if (!$cashBox) {
                    DB::rollBack();
                    throw new \Exception(" معرف الخزنه cashBoxId{$cashBoxId}المستخدم ليس له خزنة.");
                }
            } else {
                if (is_null($authCompanyId)) {
                    DB::rollBack();
                    throw new \Exception("لا توجد شركة نشطة {$authCompanyId} للمستخدم {$this->nickname} الحالي لتحديد الخزنة الافتراضية.");
                }
                $cashBox = CashBox::query()->where('user_id', $this->id)->where('is_default', 1)->where('company_id', $authCompanyId)->first();

                if (!$cashBox) {
                    DB::rollBack();
                    throw new \Exception(" المستخدم ليس له خزنة لنفس الشركة");
                }
            }

            if (!$cashBox) {
                DB::rollBack();
                throw new \Exception("لم يتم العثور على خزنة مناسبة للمستخدم : {$this->nickname} ");
            }

            $cashBox->increment('balance', $amount);

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('User Model Deposit: فشل الإيداع.', [
                'error' => $e->getMessage(),
                'user_id' => $this->id,
                'amount' => $amount,
                'cash_box_id' => $cashBoxId, // إضافة cash_box_id لتسجيل الأخطاء
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function transfer($cashBoxId, $targetUserId, $amount, $description = null)
    {
        $amount = floatval($amount);
        if (!$this->hasAnyPermission(['super_admin', 'transfer', 'company_owner'])) {
            throw new Exception('Unauthorized: You do not have permission to transfer.');
        }

        DB::beginTransaction();

        try {
            $cashBox = $this->cashBoxes()
                ->where('id', $cashBoxId)
                ->where('company_id', $this->company_id)
                ->firstOrFail();

            if ($cashBox->balance < $amount) {
                throw new Exception('Insufficient funds in the cash box.');
            }

            $targetUser = User::findOrFail($targetUserId);
            $targetCashBox = $targetUser->cashBoxes()
                ->where('cash_type', $cashBox->cash_type)
                ->where('company_id', $this->company_id)
                ->first();

            if (!$targetCashBox) {
                throw new Exception('Target user does not have a matching cash box.');
            }

            $cashBox->decrement('balance', $amount);
            $targetCashBox->increment('balance', $amount);

            Transaction::create([
                'user_id' => $this->id,
                'cashbox_id' => $cashBox->id,
                'target_user_id' => $targetUserId,
                'target_cashbox_id' => $targetCashBox->id,
                'created_by' => $this->id,
                'company_id' => $cashBox->company_id,
                'type' => 'تحويل',
                'amount' => $amount,
                'balance_before' => $cashBox->balance + $amount,
                'balance_after' => $cashBox->balance,
                'description' => $description,
                'original_transaction_id' => null,
            ]);

            // تأكد من أن $transaction معرف هنا قبل استخدامه
            // إذا لم يكن معرفًا، ستحتاج إلى جلب المعاملة الأولى بعد إنشائها
            // أو تغيير منطق إنشاء المعاملة الثانية
            // For example: $transactionId = $transaction->id;
            // ثم استخدم $transactionId في المعاملة الثانية
            Transaction::create([
                'user_id' => $targetUserId,
                'cashbox_id' => $targetCashBox->id,
                'target_user_id' => $this->id,
                'target_cashbox_id' => $cashBox->id,
                'created_by' => $this->id,
                'company_id' => $targetCashBox->company_id,
                'type' => 'إيداع',
                'amount' => $amount,
                'balance_before' => $targetCashBox->balance - $amount,
                'balance_after' => $targetCashBox->balance,
                'description' => 'Received from transfer',
                'original_transaction_id' => null, // يجب أن يكون هنا $transaction->id
            ]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('User Model Transfer: فشل التحويل.', [
                'error' => $e->getMessage(),
                'user_id' => $this->id,
                'amount' => $amount,
                'trace' => $e->getTraceAsString()
            ]);
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
     * إرجاع جميع معرفات المستخدمين التابعين للمستخدم الحالي داخل الشركة النشطة فقط.
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
        if (($key = array_search($this->id, $descendants)) !== false) {
            unset($descendants[$key]);
        }
        return array_values($descendants);
    }

    /**
     * Ensure the user has a cash box for every company they belong to.
     */
    public function ensureCashBoxesForAllCompanies(): void
    {
        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            $companies = Company::all();
        } else {
            $companies = $this->companies;
        }

        $companyIds = $companies->pluck('id')->toArray();

        app(CashBoxService::class)->ensureCashBoxesForUserCompanies($this, $companyIds, $this->created_by ?? $this->id);
    }
}

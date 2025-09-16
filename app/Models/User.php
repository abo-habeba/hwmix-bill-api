<?php

namespace App\Models;

use Exception;
use App\Traits\Scopes;
use App\Traits\HasImages;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
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
use Illuminate\Database\Eloquent\Relations\HasMany; // إضافة HasMany
use Illuminate\Database\Eloquent\Relations\HasOne;   // إضافة HasOne


/**
 * @method void deposit(float|int $amount)
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Translatable, HasRoles, HasApiTokens, Filterable, Scopes, HasPermissions, LogsActivity, HasImages;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $guarded = [];

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
            app(CashBoxService::class)->ensureCashBoxForUser($user);
            // يجب تحديث هذه الدالة لإنشاء سجل CompanyUser الافتراضي أيضاً
            // For new users, ensure a default CompanyUser entry is created if not already (e.g., for system default company)
        });
    }

    public function trans()
    {
        return $this->morphMany(Translation::class, 'model');
    }

    /**
     * علاقة المستخدم بالخزنة الافتراضية.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cashBoxDefault()
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
     * علاقة المستخدم بالشركات التي ينتمي إليها عبر جدول الوسيط company_user.
     */
    // يجيب الشركات مباشرة (علاقة Many To Many)
    public function companies(): BelongsToMany
    {
        return $this
            ->belongsToMany(Company::class, 'company_user', 'user_id', 'company_id')
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

    // يجيب سجلات العضوية نفسها (Pivot Model)
    public function companyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'user_id');
    }

    // يجيب العضوية الخاصة بالشركة النشطة
    public function activeCompanyUser(): HasOne
    {
        $activeCompanyId = $this->company_id ?? null;

        return $this->hasOne(CompanyUser::class, 'user_id')
            ->where('company_id', $activeCompanyId);
    }


    public function companyUsersCash()
    {
        return $this
            ->belongsToMany(Company::class, 'user_company_cash', 'user_id', 'company_id')
            ->withPivot('cash_box_id', 'created_by');
    }

    public function cashBoxes(): HasMany
    {
        return $this->hasMany(CashBox::class, 'user_id');
    }


    /**
     * الحصول على رصيد صندوق نقدية محدد أو صندوق النقدية الافتراضي للشركة النشطة.
     *
     * @param int|null $id معرف صندوق النقدية.
     * @return float
     */
    public function balanceBox($id = null): float
    {
        $cashBox = null;
        if ($id) {
            // البحث عن صندوق نقدية محدد يخص هذا المستخدم والشركة النشطة
            $cashBox = $this->cashBoxes()->where('id', $id)
                ->where('company_id', Auth::user()->company_id ?? null)
                ->first();
        } else {
            // استدعاء العلاقة كدالة ثم استخدام first() للحصول على النموذج
            $cashBox = $this->cashBoxDefault()->first();
        }
        return $cashBox ? $cashBox->balance : 0.0;
    }

    public function cashBoxesByCompany(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->cashBoxes()->where('company_id', Auth::user()->company_id ?? null)->get();
    }

    public function createdRoles()
    {
        return $this->hasManyThrough(
            Role::class,
            RoleCompany::class, // يفترض وجود RoleCompany Model أو جدول وسيط
            'created_by',
            'id',
            'id',
            'role_id'
        );
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'user_id');
    }

    public function createdInstallments(): HasMany
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * خصم مبلغ من رصيد المستخدم (خزنته).
     *
     * @param float $amount المبلغ المراد سحبه.
     * @param int|null $cashBoxId معرف صندوق النقدية المحدد (اختياري).
     * @return bool True عند النجاح.
     * @throws Exception عند الفشل (مثل عدم وجود خزنة أو رصيد غير كافٍ).
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
            } else {
                // البحث عن الخزنة الافتراضية للمستخدم الحالي ($this) والتي تتبع الشركة النشطة للمستخدم الموثق
                if (is_null($authCompanyId)) {
                    DB::rollBack();
                    throw new Exception("لا توجد شركة نشطة للمستخدم الحالي لتحديد الخزنة الافتراضية.");
                }
                $cashBox = CashBox::query()->where('user_id', $this->id)->where('is_default', true)->where('company_id', $authCompanyId)->first();
            }

            if (!$cashBox) {
                DB::rollBack();
                throw new Exception("لم يتم العثور على خزنة مناسبة للمستخدم : {$this->nickname}");
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
     * @throws Exception عند الفشل (مثل عدم وجود خزنة).
     */

    public function deposit(float $amount, $cashBoxId = null): bool
    {
        $amount = floatval($amount);
        DB::beginTransaction();
        $authUser = Auth::user();
        $authCompanyId = $authUser->company_id ?? null;
        try {
            $cashBox = null;
            if ($cashBoxId) {
                $cashBox = CashBox::query()->where('id', $cashBoxId)->where('user_id', $this->id)->first();
                if (!$cashBox) {
                    DB::rollBack();
                    throw new Exception(" معرف الخزنه cashBoxId{$cashBoxId}المستخدم ليس له خزنة.");
                }
            } else {
                if (is_null($authCompanyId)) {
                    DB::rollBack();
                    throw new Exception("لا توجد شركة نشطة {$authCompanyId} للمستخدم {$this->nickname} الحالي لتحديد الخزنة الافتراضية.");
                }
                $cashBox = CashBox::query()->where('user_id', $this->id)->where('is_default', 1)->where('company_id', $authCompanyId)->first();

                if (!$cashBox) {
                    DB::rollBack();
                    throw new Exception(" المستخدم ليس له خزنة لنفس الشركة");
                }
            }

            if (!$cashBox) {
                DB::rollBack();
                throw new Exception("لم يتم العثور على خزنة مناسبة للمستخدم : {$this->nickname} ");
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
        // التحقق من الصلاحيات يجب أن يتم بناءً على السياق (شركة معينة)
        // قد تحتاج إلى صلاحية مثل 'transfer_in_company'
        if (!$this->hasAnyPermission(['super_admin', 'transfer'])) { // أزلت 'company_owner' هنا لتبسيط المثال، لكن يجب أن تحدد الصلاحيات بدقة
            throw new Exception('Unauthorized: You do not have permission to transfer.');
        }

        DB::beginTransaction();

        try {
            // جلب صندوق النقدية للمرسل مع التأكد أنه يخص الشركة النشطة للمستخدم الموثق
            $authCompanyId = Auth::user()->company_id ?? null;
            if (is_null($authCompanyId)) {
                throw new Exception("لا توجد شركة نشطة للمستخدم الموثق لإجراء التحويل.");
            }

            $cashBox = $this->cashBoxes()
                ->where('id', $cashBoxId)
                ->where('company_id', $authCompanyId) // استخدام الشركة النشطة للمستخدم الموثق
                ->firstOrFail();

            if ($cashBox->balance < $amount) {
                throw new Exception('Insufficient funds in the cash box.');
            }

            $targetUser = User::findOrFail($targetUserId);
            // جلب صندوق نقدية المستلم بنفس نوع النقدية ونفس الشركة النشطة
            $targetCashBox = $targetUser->cashBoxes()
                ->where('cash_type', $cashBox->cash_type)
                ->where('company_id', $authCompanyId) // المستلم يجب أن يكون لديه خزنة في نفس الشركة
                ->first();

            if (!$targetCashBox) {
                throw new Exception('Target user does not have a matching cash box in the active company.');
            }

            $cashBox->decrement('balance', $amount);
            $targetCashBox->increment('balance', $amount);

            // إنشاء المعاملة للمرسل
            $senderTransaction = Transaction::create([
                'user_id' => $this->id,
                'cashbox_id' => $cashBox->id,
                'target_user_id' => $targetUserId,
                'target_cashbox_id' => $targetCashBox->id,
                'created_by' => $this->id,
                'company_id' => $authCompanyId,
                'type' => 'تحويل_صادر', // نوع المعاملة ليكون أوضح
                'amount' => $amount,
                'balance_before' => $cashBox->balance + $amount, // الرصيد قبل التحديث
                'balance_after' => $cashBox->balance, // الرصيد بعد التحديث
                'description' => $description,
                'original_transaction_id' => null,
            ]);

            // إنشاء المعاملة للمستلم
            Transaction::create([
                'user_id' => $targetUserId,
                'cashbox_id' => $targetCashBox->id,
                'target_user_id' => $this->id,
                'target_cashbox_id' => $cashBox->id,
                'created_by' => $this->id,
                'company_id' => $authCompanyId,
                'type' => 'تحويل_وارد', // نوع المعاملة ليكون أوضح
                'amount' => $amount,
                'balance_before' => $targetCashBox->balance - $amount, // الرصيد قبل التحديث
                'balance_after' => $targetCashBox->balance, // الرصيد بعد التحديث
                'description' => 'Received from transfer',
                'original_transaction_id' => $senderTransaction->id, // ربط المعاملتين
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }

    /**
     * إرجاع جميع معرفات المستخدمين التابعين للمستخدم الحالي داخل الشركة النشطة فقط.
     * يجب أن تأخذ هذه الدالة في الاعتبار العلاقة الجديدة CompanyUser
     * وتعتمد على 'created_by' في جدول company_user.
     *
     * @return array
     */
    public function getDescendantUserIds(): array
    {
        // يجب أن نحدد الشركة النشطة بشكل واضح
        $companyId = Auth::user()->company_id ?? null; // أو أي طريقة أخرى للحصول على الشركة النشطة

        if (is_null($companyId)) {
            return []; // إذا لم تكن هناك شركة نشطة، لا يوجد تابعون في سياق الشركة
        }

        $descendants = [];
        $stack = [$this->id];

        while (!empty($stack)) {
            $parentId = array_pop($stack);
            // البحث عن التابعين في جدول company_user بناءً على created_by_id في company_user
            // هذا يتطلب أن يكون لديك حقل created_by_id في جدول company_user يشير إلى user_id
            // إذا كان 'created_by' في company_user يشير إلى user_id في users، فهذا هو الصحيح.
            $children = CompanyUser::where('created_by', $parentId)
                ->where('company_id', $companyId)
                ->pluck('user_id') // نأخذ user_id من CompanyUser
                ->toArray();

            foreach ($children as $childUserId) {
                if (!in_array($childUserId, $descendants)) {
                    $descendants[] = $childUserId;
                    $stack[] = $childUserId;
                }
            }
        }
        // إزالة المستخدم الحالي من قائمة التابعين إذا كان موجودًا
        if (($key = array_search($this->id, $descendants)) !== false) {
            unset($descendants[$key]);
        }
        return array_values($descendants);
    }

    /**
     * التأكد من وجود صناديق نقدية للمستخدم في جميع الشركات التي ينتمي إليها.
     * يجب تحديث هذه الدالة لتعمل مع هيكل CompanyUser الجديد.
     */
    public function ensureCashBoxesForAllCompanies(): void
    {
        // إذا كان المستخدم super_admin، يتم إنشاء صناديق نقدية له في جميع الشركات
        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            $companies = Company::all();
        } else {
            // جلب الشركات التي يرتبط بها المستخدم مباشرة عبر جدول company_user
            // لاحظ أن العلاقة companies() هنا ستجلب Companies المرتبطة بـ CompanyUser
            $companies = $this->companies; // هذه العلاقة تجلب موديلات Company
        }

        $companyIds = $companies->pluck('id')->toArray();

        // تمرير معرفات الشركات إلى خدمة CashBoxService
        // created_by سيكون هو معرف المستخدم الذي أضاف هذا المستخدم للشركة (من جدول company_user)
        // أو إذا كان المستخدم هو نفسه الذي أنشأ، فسيكون $this->id
        // هذه الدالة في CashBoxService ستحتاج إلى تحديث لتتعامل مع CompanyUser
        app(CashBoxService::class)->ensureCashBoxesForUserCompanies($this, $companyIds, $this->created_by ?? $this->id);
    }

    public function getVisibleCompaniesForUser()
    {
        // Debugging: تحقق من محتوى $this->companies

        // Debugging: تأكد من أن $this->companies هو Collection فارغة إذا لم تكن هناك علاقات
        if ($this->companies->isEmpty()) {
        } else {
        }

        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            return \App\Models\Company::all();
        }
        return $this->companies;
    }
}

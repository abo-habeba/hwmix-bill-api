<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\User;
use App\Models\CashBox;
use App\Models\CashBoxType;
use Illuminate\Http\Request;
use App\Services\CashBoxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\User\UserRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\User\UserUpdateRequest;

if (!function_exists('perm_key')) {
    function perm_key(string $permission)
    {
        return $permission;
    }
}

class UserController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'companies.logo',
            'cashBoxes',
            'cashBoxeDefault',
            'creator',
        ];
    }
    public function index(Request $request)
    {
        $authUser = Auth::user();
        try {
            if (!$authUser) {
                return api_unauthorized('يجب تسجيل الدخول.');
            }
            $query = User::with($this->relations);
            // تطبيق منطق الصلاحيات بترتيب هرمي
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المدير العام يرى كل المستخدمين، لا تصفية
            } elseif ($authUser->hasPermissionTo(perm_key('admin.company')) || $authUser->hasPermissionTo(perm_key('users.view_all'))) {  // صلاحية ادارة الشركة
                $activeCompanyId = $authUser->company_id;
                if (!$activeCompanyId) {
                    $query->whereRaw('0 = 1');  // لا يوجد شركة نشطة، لا يرى شيئًا
                } else {
                    $query->where(function (Builder $q) use ($activeCompanyId) {
                        $q
                            ->where('users.company_id', $activeCompanyId)
                            ->orWhereHas('companies', function (Builder $q2) use ($activeCompanyId) {
                                $q2->where('companies.id', $activeCompanyId);
                            });
                    });
                }
            } elseif ($authUser->hasPermissionTo(perm_key('users.view_children'))) {
                $query->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('users.view_self'))) {
                $query->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض المستخدمين.');
            }

            // تطبيق فلاتر البحث
            if ($request->filled('nickname')) {
                $query->where('nickname', 'like', '%' . $request->input('nickname') . '%');
            }
            if ($request->filled('email')) {
                $query->where('email', 'like', '%' . $request->input('email') . '%');
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('created_at_from')) {
                $query->where('created_at', '>=', $request->input('created_at_from') . ' 00:00:00');
            }
            if ($request->filled('created_at_to')) {
                $query->where('created_at', '<=', $request->input('created_at_to') . ' 23:59:59');
            }

            // الفرز والتصفح
            $perPage = max(1, $request->input('per_page', 10));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $users = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($users->isEmpty()) {
                return api_success([], 'لم يتم العثور على مستخدمين.');
            } else {
                return api_success(UserResource::collection($users), 'تم جلب المستخدمين بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function store(UserRequest $request)
    {
        $authUser = Auth::user();

        // الشرط الوحيد لفحص الصلاحيات كما هو مطلوب
        if (!$authUser || (
            !$authUser->hasPermissionTo(perm_key('admin.super')) &&
            !$authUser->hasPermissionTo(perm_key('users.create')) &&
            !$authUser->hasPermissionTo(perm_key('admin.company'))
        )) {
            return api_forbidden('ليس لديك صلاحية لإنشاء مستخدمين.');
        }

        DB::beginTransaction();
        try {
            $validatedData = $request->validated();

            // تعيين created_by: دائماً المستخدم المصادق عليه لضمان الأمان
            $validatedData['created_by'] = $authUser->id;


            // السوبر أدمن يمكنه تحديد أي company_id، وإذا لم يتم تحديده، يمكن أن يكون null أو افتراضي
            $validatedData['company_id'] = $validatedData['company_id'] ?? 1;
            $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;

            $validatedData['company_id'] = $authUser->company_id;

            // إنشاء المستخدم
            $user = User::create($validatedData);

            // إنشاء صناديق المستخدم الافتراضية لكل شركة
            $user->ensureCashBoxesForAllCompanies();

            // معالجة company_ids لربط المستخدم بالشركات الإضافية
            $companyIdsFromRequest = $request->input('company_ids', []);

            $companyIdsToSync = [];
            if (is_array($companyIdsFromRequest)) {
                foreach ($companyIdsFromRequest as $id) {
                    // التحقق من أن القيمة عدد صحيح وصالحة (أكبر من صفر)
                    if (filter_var($id, FILTER_VALIDATE_INT) !== false && (int)$id > 0) {
                        $companyId = (int)$id;

                        // تحقق إضافي للمستخدم العادي: لا يمكنه ربط المستخدم إلا بشركته النشطة
                        if ($authUser->hasPermissionTo(perm_key('users.create')) && !$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                            if ($companyId !== $authUser->company_id) {
                                Log::warning("Regular user {$authUser->id} attempted to link user {$user->id} to unauthorized company {$companyId}.");
                                continue; // تخطي هذه الشركة
                            }
                        }
                        $companyIdsToSync[$companyId] = [
                            'created_by' => $authUser->id,
                            'company_id' => $authUser->company_id,
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            // إضافة الشركة الأساسية للمستخدم (إذا لم تكن موجودة بالفعل في company_idsToSync)
            // هذا يضمن أن المستخدم مرتبط بشركته الأساسية دائماً
            if (!empty($user->company_id) && !isset($companyIdsToSync[$user->company_id])) {
                $companyIdsToSync[$user->company_id] = [
                    'created_by' => $authUser->id,
                    'company_id' => $authUser->company_id,
                    'updated_at' => now(),
                ];
            }

            $user->companies()->sync($companyIdsToSync);
            // images_ids
            if ($request->has('images_ids')) {
                $imagesIds = $request->input('images_ids');
                $user->syncImages($imagesIds, 'avatar');
            }
            $user->logCreated('بانشاء المستخدم ' . $user->nickname);
            DB::commit();
            return api_success(new UserResource($user->load($this->relations)), 'تم إنشاء المستخدم بنجاح');
        } catch (Throwable $e) {
            DB::rollback();
            Log::error("فشل إنشاء المستخدم: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }
    public function show(User $user)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        $query = User::where('id', $user->id)->with($this->relations);

        // تطبيق منطق الصلاحيات بترتيب هرمي
        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            // المدير العام يرى أي مستخدم، لا تصفية إضافية
        } elseif ($authUser->id === $user->id) {
        } elseif ($authUser->hasPermissionTo(perm_key('admin.company')) || $authUser->hasPermissionTo(perm_key('users.view_all'))) {
            $activeCompanyId = $authUser->company_id;
            if (!$activeCompanyId) {
                return api_forbidden('لا توجد شركة نشطة.');
            }
            $query->where(function (Builder $q) use ($activeCompanyId) {
                $q
                    ->where('users.company_id', $activeCompanyId)
                    ->orWhereHas('companies', function (Builder $q2) use ($activeCompanyId) {
                        $q2->where('companies.id', $activeCompanyId);
                    });
            });
        } elseif ($authUser->hasPermissionTo(perm_key('users.view_children'))) {
            $query->whereCreatedByUserOrChildren();
        } elseif ($authUser->hasPermissionTo(perm_key('users.view_self'))) {
            $query->whereCreatedByUser();
        } else {
            return api_forbidden('ليس لديك صلاحية لعرض هذا المستخدم.');
        }

        // تنفيذ الاستعلام للحصول على المستخدم بعد تطبيق شروط الصلاحيات
        $authorizedUser = $query->first();

        // التحقق النهائي: إذا لم يتم العثور على المستخدم أو كان المستخدم الذي تم العثور عليه ليس هو المستخدم المستهدف
        if (!$authorizedUser || $authorizedUser->id !== $user->id) {
            return api_not_found('المستخدم غير موجود أو ليس لديك صلاحية لعرضه.');
        }

        return api_success(new UserResource($authorizedUser), 'تم جلب بيانات المستخدم بنجاح');
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }
        $canUpdate = $authUser->hasPermissionTo(perm_key('admin.super')) ||
            $authUser->hasPermissionTo(perm_key('admin.company')) ||
            ($authUser->hasPermissionTo(perm_key('users.update_all')) && $authUser->belongsToCurrentCompany()) ||
            ($authUser->hasPermissionTo(perm_key('users.update_children')) && $authUser->createdByUserOrChildren($user)) ||
            ($authUser->hasPermissionTo(perm_key('users.update_self')) && $authUser->createdByCurrentUser($user));
        if (!$canUpdate) {
            return api_forbidden('ليس لديك صلاحية لتحديث هذا المستخدم.');
        }
        DB::beginTransaction();
        try {
            $validated = $request->validated();
            if (!empty($validated['password'])) {
                $user->password = $validated['password'];
            }
            $validated['company_id'] = $validated['company_id'] ?? $user->company_id;
            $validated['created_by'] = $validated['created_by'] ?? $user->created_by;
            $user->update($validated);
            $companyIds = collect($validated['company_ids'] ?? [])
                ->filter(fn($id) => !empty($id) && is_numeric($id)) // ← يشيل القيم الغلط
                ->values()
                ->toArray();

            if (isset($validated['company_ids'])) {
                $oldCompanyIds = $user->companies()->pluck('companies.id')->toArray();
                $pivotData = [];
                foreach ($companyIds as $companyId) {
                    $pivotData[$companyId] = [
                        'created_by' => $authUser->id,
                        'company_id' => $authUser->company_id,
                        'updated_at' => now(),
                    ];
                }
                $user->companies()->sync($pivotData);
                $newCompanyIds = array_diff($validated['company_ids'], $oldCompanyIds);

                app(CashBoxService::class)->ensureCashBoxesForUserCompanies($user, $newCompanyIds);
            }
            if (isset($validated['permissions']) && is_array($validated['permissions'])) {
                $user->syncPermissions($validated['permissions']);
            }
            // images_ids
            if ($request->has('images_ids')) {
                $imagesIds = $request->input('images_ids');
                $user->syncImages($imagesIds, 'avatar');
            }
            $user->logUpdated('المستخدم ' . $user->nickname);
            DB::commit();
            return api_success(new UserResource($user->load($this->relations)), 'تم تحديث المستخدم بنجاح');
        } catch (Throwable $e) {
            DB::rollback();
            return api_exception($e);
        }
    }

    public function destroy(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }
        $userIds = $request->input('item_ids');
        if (!$userIds || !is_array($userIds) || empty($userIds)) {
            return api_error('لم يتم تحديد معرفات المستخدمين بشكل صحيح', [], 400);
        }
        DB::beginTransaction();
        try {
            $usersToDelete = User::whereIn('id', $userIds);
            if ($authUser->hasPermissionTo(perm_key('admin.super')) || $authUser->hasPermissionTo(perm_key('users.delete_all'))) {
                if ($authUser->hasPermissionTo(perm_key('users.delete_all'))) {
                    if (!$authUser->company_id) {
                        DB::rollBack();
                        return api_forbidden('يجب تحديد شركة نشطة للحذف.');
                    }
                    $usersToDelete->where(function (Builder $q) use ($authUser) {
                        $q->where('users.company_id', $authUser->company_id)
                            ->orWhereHas('companies', function (Builder $q2) use ($authUser) {
                                $q2->where('companies.id', $authUser->company_id);
                            });
                    });
                }
            } elseif ($authUser->hasPermissionTo(perm_key('admin.company'))) {
                $activeCompanyId = $authUser->company_id;
                if (!$activeCompanyId) {
                    return api_forbidden('يجب تحديد شركة نشطة للحذف.');
                }
                $usersToDelete->where(function (Builder $q) use ($activeCompanyId) {
                    $q->where('users.company_id', $activeCompanyId)
                        ->orWhereHas('companies', function (Builder $q2) use ($activeCompanyId) {
                            $q2->where('companies.id', $activeCompanyId);
                        });
                });
            } elseif ($authUser->hasPermissionTo(perm_key('users.delete_children'))) {
                $usersToDelete
                    ->where('created_by', $authUser->id)
                    ->orWhereIn('created_by', $authUser->children->pluck('id')->toArray());
            } elseif ($authUser->hasPermissionTo(perm_key('users.delete_self'))) {
                if (count($userIds) !== 1 || $userIds[0] !== $authUser->id) {
                    return api_forbidden('يمكنك فقط حذف حسابك الشخصي بهذا التصريح.');
                }
                $usersToDelete->where('id', $authUser->id);
            } else {
                return api_forbidden('ليس لديك صلاحية لحذف أي من المستخدمين المحددين.');
            }
            $authorizedUsers = $usersToDelete->get();
            if ($authorizedUsers->isEmpty()) {
                return api_forbidden('لم يتم العثور على مستخدمين أو ليس لديك صلاحية لحذفهم.');
            }
            $foundUserIds = $authorizedUsers->pluck('id')->toArray();
            $diff = array_diff($userIds, $foundUserIds);
            if (!empty($diff)) {
                return api_forbidden('ليس لديك صلاحية لحذف بعض المستخدمين المحددين.', ['unauthorized_ids' => array_values($diff)]);
            }
            foreach ($authorizedUsers as $user) {
                if ($user->id === $authUser->id && count($userIds) === 1) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك حذف حسابك النشط.');
                }
                $user->cashBoxes()->delete();
                $user->companies()->detach();
                $user->delete();
                $user->logForceDeleted('المستخدم ' . $user->nickname);
            }
            DB::commit();
            return api_success([], 'تم حذف المستخدمين بنجاح');
        } catch (Throwable $e) {
            DB::rollback();
            return api_exception($e);
        }
    }

    public function usersSearch(Request $request)
    {
        $authUser = Auth::user();
        try {
            if (!$authUser) {
                return api_unauthorized('يجب تسجيل الدخول.');
            }
            $query = User::query();
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المدير العام يمكنه البحث عن أي مستخدم
            } elseif ($authUser->hasPermissionTo(perm_key('admin.company'))) {
                $activeCompanyId = $authUser->company_id;
                if (!$activeCompanyId) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->where(function (Builder $q) use ($activeCompanyId) {
                        $q->where('users.company_id', $activeCompanyId)
                            ->orWhereHas('companies', function (Builder $q2) use ($activeCompanyId) {
                                $q2->where('companies.id', $activeCompanyId);
                            });
                    });
                }
            } elseif ($authUser->hasPermissionTo(perm_key('users.view_all'))) {
                $activeCompanyId = $authUser->company_id;
                if (!$activeCompanyId) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->where(function (Builder $q) use ($activeCompanyId) {
                        $q->where('users.company_id', $activeCompanyId)
                            ->orWhereHas('companies', function (Builder $q2) use ($activeCompanyId) {
                                $q2->where('companies.id', $activeCompanyId);
                            });
                    });
                }
            } elseif ($authUser->hasPermissionTo(perm_key('users.view_children'))) {
                $query->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('users.view_self'))) {
                $query->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك صلاحية للبحث عن المستخدمين.');
            }
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                $query->where('id', '<>', $authUser->id);
            }
            if ($request->filled('search')) {
                $search = $request->input('search');
                if (strlen($search) < 4) {
                    $query->where('id', $search);
                } else {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('id', $search)
                            ->orWhere('nickname', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    });
                }
            }
            $perPage = max(1, $request->input('per_page', 10));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');
            $users = $query->with('companies')->orderBy($sortField, $sortOrder)->paginate($perPage);
            return api_success(UserResource::collection($users), 'تم البحث عن المستخدمين بنجاح');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function setDefaultCashBox(User $user, $cashBoxId)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }
        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            // المدير العام يمكنه تغيير الخزنة الافتراضية لأي مستخدم
        } elseif ($authUser->hasPermissionTo(perm_key('admin.company'))) {
            // مدير الشركة يمكنه تغيير الخزنة الافتراضية للمستخدمين في شركته
            if (!$authUser->company_id || ($user->company_id !== $authUser->company_id && !$user->companies->contains($authUser->company_id))) {
                return api_forbidden('ليس لديك صلاحية لتغيير الخزنة لهذا المستخدم.');
            }
        } elseif ($authUser->id === $user->id) {
            // المستخدم يمكنه تغيير الخزنة الافتراضية الخاصة به فقط
        } else {
            return api_forbidden('ليس لديك صلاحية لتعيين الخزنة الافتراضية لهذا المستخدم.');
        }
        DB::beginTransaction();
        try {
            $cashBox = CashBox::where('user_id', $user->id)
                ->where('id', $cashBoxId)
                ->first();
            if (!$cashBox) {
                DB::rollBack();
                return api_not_found('الخزنة غير موجودة أو لا تتبع هذا المستخدم.');
            }
            $user->cashBoxes()->update(['is_default' => false]);
            $cashBox->update(['is_default' => true]);
            $user->logUpdated('بتعيين الخزنة الافتراضية ' . $cashBox->name . ' للمستخدم ' . $user->nickname);
            DB::commit();
            return api_success([], 'تم تعيين الخزنة الافتراضية بنجاح');
        } catch (Throwable $e) {
            DB::rollback();
            return api_exception($e);
        }
    }

    /**
     * تغيير الشركة النشطة للمستخدم.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeCompany(Request $request, User $user)
    {
        $authUser = Auth::user();
        if (!$authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company'), perm_key('companies.change_active_company')])) {
            return api_forbidden('ليس لديك صلاحية لتغيير الشركة.');
        }
        $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
        ]);
        $user->company_id = $request->input('company_id');
        $user->save();
        return api_success(new UserResource($user->load($this->relations)), 'تم تغيير الشركة بنجاح');
    }
}

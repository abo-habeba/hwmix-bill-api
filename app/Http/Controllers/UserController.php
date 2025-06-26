<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Http\Resources\User\UserResource;
use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            'companies',
            'cashBoxes',
            'cashBoxeDefault',
            'creator',
        ];
    }

    public function index(Request $request)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        try {
            if (!$authUser) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required.'
                ], 401);
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
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to view users.'
                ], 403);
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

            return UserResource::collection($users)->additional([
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('User index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $authUser ? $authUser->id : null,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب المستخدمين.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => app()->isLocal() ? $e->getTrace() : null,
                'user_id' => $authUser?->id,
            ], 500);
        }
    }

    public function store(UserRequest $request)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        if (!$authUser || (
            !$authUser->hasPermissionTo(perm_key('admin.super')) ||
            !$authUser->hasPermissionTo(perm_key('users.create')) ||
            !$authUser->hasPermissionTo(perm_key('admin.company'))
        )) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to create users.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $validatedData = $request->validated();

            // منطق تعيين company_id و created_by بناءً على الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // السوبر أدمن يمكنه تحديد company_id و created_by
                // إذا لم يتم تحديد company_id صراحةً، يمكن أن يكون null أو يتم تعيينه لاحقًا
                $validatedData['company_id'] = isset($validatedData['company_ids']) && is_array($validatedData['company_ids'])
                    ? $validatedData['company_ids'][0]  // استخدام أول شركة في القائمة كـ company_id الأساسي
                    : ($validatedData['company_id'] ?? null);
                $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;
            } elseif ($authUser->hasPermissionTo(perm_key('admin.company'))) {
                // مدير الشركة ينشئ مستخدمين لشركته النشطة أو الشركات التي يديرها
                $validatedData['created_by'] = $authUser->id;

                // إذا تم تحديد company_ids، يجب التأكد أنها ضمن الشركات التي يديرها مدير الشركة
                if (isset($validatedData['company_ids']) && is_array($validatedData['company_ids'])) {
                    $authCompanyIds = $authUser->companies->pluck('id')->toArray();
                    foreach ($validatedData['company_ids'] as $companyId) {
                        if (!in_array($companyId, $authCompanyIds)) {
                            DB::rollBack();
                            return response()->json(['error' => 'Forbidden', 'message' => 'You can only create users for companies you manage.'], 403);
                        }
                    }
                    // تعيين company_id الرئيسي للمستخدم الجديد ليكون الشركة النشطة للمدير أو أول شركة في الـ company_ids المدخلة
                    $validatedData['company_id'] = $authUser->company_id ?? $validatedData['company_ids'][0];
                } else {
                    // إذا لم يتم تحديد company_ids، يتم تعيين المستخدم للشركة النشطة للمدير
                    if (!$authUser->company_id) {
                        DB::rollBack();
                        return response()->json(['error' => 'Forbidden', 'message' => 'Your active company is not set to create users.'], 403);
                    }
                    $validatedData['company_id'] = $authUser->company_id;
                    $validatedData['company_ids'] = $validatedData['company_ids'] ?? [$authUser->company_id];  // للتزامن لاحقًا
                }
            } else {  // users.create فقط
                // المستخدم العادي ينشئ مستخدمين لشركته النشطة فقط
                if (!$authUser->company_id) {
                    DB::rollBack();
                    return response()->json(['error' => 'Forbidden', 'message' => 'Your active company is not set to create users.'], 403);
                }
                $validatedData['company_id'] = $authUser->company_id;
                $validatedData['created_by'] = $authUser->id;

                // يجب أن تكون الشركة النشطة للمستخدم في company_ids
                if (isset($validatedData['company_ids']) && is_array($validatedData['company_ids'])) {
                    $validatedData['company_ids'] = [$authUser->company_id];  // تعيين تلقائي إذا لم يتم تحديدها
                }
            }

            $user = User::create($validatedData);
            $cashBoxType = CashBoxType::where('description', 'النوع الافتراضي للسيستم')->first();

            if ($cashBoxType) {
                CashBox::create([
                    'name' => 'نقدي',
                    'balance' => 0,
                    'cash_box_type_id' => $cashBoxType->id,
                    'is_default' => true,
                    'account_number' => $user->id,
                    'user_id' => $user->id,
                    'created_by' => $user->id,  // الخزنة تُنشأ بواسطة المستخدم نفسه
                    'company_id' => $user->company_id,  // ربط الخزنة بالشركة الأساسية للمستخدم
                ]);
            } else {
                throw new \Exception('نوع الخزنة الافتراضي غير موجود.');
            }

            if (!empty($validatedData['company_ids'])) {
                $pivotData = [];
                foreach ($validatedData['company_ids'] as $companyId) {
                    $pivotData[$companyId] = [
                        'created_by' => $authUser->id,
                        'updated_at' => now(),
                    ];
                }
                $user->companies()->sync($pivotData);
            }

            $user->logCreated('بانشاء المستخدم ' . $user->nickname);
            DB::commit();
            return new UserResource($user->load($this->relations));
        } catch (Throwable $e) {
            DB::rollback();
            Log::error('User store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $authUser ? $authUser->id : null,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء حفظ المستخدم.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => app()->isLocal() ? $e->getTrace() : null,
                'user_id' => $authUser?->id,
            ], 500);
        }
    }

    public function show(User $user)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        $query = User::where('id', $user->id)->with($this->relations);

        // تطبيق منطق الصلاحيات بترتيب هرمي
        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            // المدير العام يرى أي مستخدم، لا تصفية إضافية
        } elseif ($authUser->id === $user->id) {
        } elseif ($authUser->hasPermissionTo(perm_key('admin.company')) || $authUser->hasPermissionTo(perm_key('users.view_all'))) {
            $activeCompanyId = $authUser->company_id;
            if (!$activeCompanyId) {
                // إذا لم تكن هناك شركة نشطة، لا يمكن للمستخدم رؤية أي شيء ضمن هذا النطاق
                return response()->json(['error' => 'Forbidden', 'message' => 'No active company set for your role.'], 403);
            }
            // يجب أن يكون المستخدم المعروض مرتبطًا بالشركة النشطة للمدير أو لديه صلاحية view_all
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
            // إذا لم يطابق أي من الصلاحيات المذكورة أعلاه، فليس لديه إذن
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to view this user.'
            ], 403);
        }

        // تنفيذ الاستعلام للحصول على المستخدم بعد تطبيق شروط الصلاحيات
        $authorizedUser = $query->first();

        // التحقق النهائي: إذا لم يتم العثور على المستخدم أو كان المستخدم الذي تم العثور عليه ليس هو المستخدم المستهدف
        if (!$authorizedUser || $authorizedUser->id !== $user->id) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'User not found or you do not have permission to view it.'
            ], 404);
        }

        return new UserResource($authorizedUser);
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        $canUpdate = $authUser->hasPermissionTo(perm_key('admin.super')) ||
            $authUser->hasPermissionTo(perm_key('admin.company')) ||
            ($authUser->hasPermissionTo(perm_key('users.update_any')) && $authUser->belongsToCurrentCompany()) ||
            ($authUser->hasPermissionTo(perm_key('users.update_children')) && $authUser->createdByUserOrChildren($user)) ||
            ($authUser->hasPermissionTo(perm_key('users.update_self')) && $authUser->createdByCurrentUser($user));

        if (!$canUpdate) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to update this user.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $validated = $request->validated();

            if (!empty($validated['password'])) {
                $user->password = $validated['password'];
            }

            // ثوابت للحفاظ على القيم القديمة إن لم تُمرر
            $validated['company_id'] = $validated['company_id'] ?? $user->company_id;
            $validated['created_by'] = $validated['created_by'] ?? $user->created_by;

            $user->update($validated);

            if (isset($validated['company_ids']) && is_array($validated['company_ids'])) {
                $pivotData = [];
                foreach ($validated['company_ids'] as $companyId) {
                    $pivotData[$companyId] = [
                        'created_by' => $authUser->id,
                        'updated_at' => now(),
                    ];
                }
                $user->companies()->sync($pivotData);
            }

            if (isset($validated['permissions']) && is_array($validated['permissions'])) {
                $user->syncPermissions($validated['permissions']);
            }

            $user->logUpdated('المستخدم ' . $user->nickname);
            DB::commit();
            return new UserResource($user->load($this->relations));
        } catch (Throwable $e) {
            DB::rollback();

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تحديث المستخدم.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => app()->isLocal() ? $e->getTrace() : null,
                'user_id' => $authUser?->id,
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        $userIds = $request->input('item_ids');

        if (!$userIds || !is_array($userIds) || empty($userIds)) {
            return response()->json(['error' => 'Invalid or empty user IDs provided'], 400);
        }

        DB::beginTransaction();
        try {
            $usersToDelete = User::whereIn('id', $userIds);

            if ($authUser->hasPermissionTo(perm_key('admin.super')) || $authUser->hasPermissionTo(perm_key('users.delete_any'))) {
                // المدير العام أو من لديه صلاحية الحذف الشامل يمكنه حذف أي مستخدم
                // إذا كان users.delete_any فيجب أن يكون ضمن نطاق الشركة النشطة للمستخدم
                if ($authUser->hasPermissionTo(perm_key('users.delete_any'))) {
                    if (!$authUser->company_id) {
                        DB::rollBack();
                        return response()->json(['error' => 'Forbidden', 'message' => 'Your active company is not set to delete users.'], 403);
                    }
                    $usersToDelete->where(function (Builder $q) use ($authUser) {
                        $q
                            ->where('users.company_id', $authUser->company_id)
                            ->orWhereHas('companies', function (Builder $q2) use ($authUser) {
                                $q2->where('companies.id', $authUser->company_id);
                            });
                    });
                }
            } elseif ($authUser->hasPermissionTo(perm_key('admin.company'))) {
                $activeCompanyId = $authUser->company_id;
                if (!$activeCompanyId) {
                    return response()->json(['error' => 'Forbidden', 'message' => 'No active company set for owner deletion.'], 403);
                }
                // مدير الشركة يمكنه حذف المستخدمين المرتبطين بشركته النشطة
                $usersToDelete->where(function (Builder $q) use ($activeCompanyId) {
                    $q
                        ->where('users.company_id', $activeCompanyId)
                        ->orWhereHas('companies', function (Builder $q2) use ($activeCompanyId) {
                            $q2->where('companies.id', $activeCompanyId);
                        });
                });
            } elseif ($authUser->hasPermissionTo(perm_key('users.delete_children'))) {
                // يمكنه حذف المستخدمين الذين أنشأهم هو أو تابعوه
                $usersToDelete
                    ->where('created_by', $authUser->id)
                    ->orWhereIn('created_by', $authUser->children->pluck('id')->toArray());  // افتراض وجود علاقة children
            } elseif ($authUser->hasPermissionTo(perm_key('users.delete_self'))) {
                if (count($userIds) !== 1 || $userIds[0] !== $authUser->id) {
                    return response()->json(['error' => 'You can only delete your own account with this permission.'], 403);
                }
                $usersToDelete->where('id', $authUser->id);
            } else {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to delete any of the specified users.'
                ], 403);
            }

            $authorizedUsers = $usersToDelete->get();

            if ($authorizedUsers->isEmpty()) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'No users found or you do not have permission to delete any of the specified users.'
                ], 403);
            }

            $foundUserIds = $authorizedUsers->pluck('id')->toArray();
            $diff = array_diff($userIds, $foundUserIds);

            if (!empty($diff)) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'You do not have permission to delete one or more of the specified users.',
                    'unauthorized_ids' => array_values($diff)
                ], 403);
            }

            foreach ($authorizedUsers as $user) {
                // التأكد من أن المستخدم لا يحاول حذف نفسه إذا كان الوحيد الذي يتم حذفه
                if ($user->id === $authUser->id && count($userIds) === 1) {
                    DB::rollBack();
                    return response()->json(['error' => 'Forbidden', 'message' => 'You cannot delete your own active account.'], 403);
                }
                $user->cashBoxes()->delete();  // حذف الصناديق النقدية المرتبطة
                $user->companies()->detach();  // فصل العلاقات مع الشركات
                $user->delete();  // حذف المستخدم
                $user->logForceDeleted('المستخدم ' . $user->nickname);
            }

            DB::commit();
            return response()->json(['message' => 'Users deleted successfully'], 200);
        } catch (Throwable $e) {
            DB::rollback();
            Log::error('User deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $authUser ? $authUser->id : null,
            ]);
            return response()->json([
                'message' => 'حدث خطأ أثناء حذف المستخدمين.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => app()->isLocal() ? $e->getTrace() : null,
                'user_id' => $authUser?->id,
            ], 500);
        }
    }

    public function usersSearch(Request $request)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        try {
            if (!$authUser) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required.'
                ], 401);
            }

            $query = User::query();

            // تطبيق منطق الصلاحيات على دالة البحث
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المدير العام يمكنه البحث عن أي مستخدم
            } elseif ($authUser->hasPermissionTo(perm_key('admin.company'))) {
                $activeCompanyId = $authUser->company_id;
                if (!$activeCompanyId) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->where(function (Builder $q) use ($activeCompanyId) {
                        $q
                            ->where('users.company_id', $activeCompanyId)
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
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to search for users.'
                ], 403);
            }

            // استبعاد المستخدم الحالي من نتائج البحث إذا لم يكن admin.super
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

            return UserResource::collection($users)->additional([
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('User search failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $authUser->id,
            ]);
            return response()->json([
                'error' => true,
                'message' => 'حدث خطأ أثناء البحث عن المستخدمين.',
            ], 500);
        }
    }

    public function setDefaultCashBox(User $user, $cashBoxId)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        // التحقق من الصلاحيات لتغيير الخزنة الافتراضية
        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            // المدير العام يمكنه تغيير الخزنة الافتراضية لأي مستخدم
        } elseif ($authUser->hasPermissionTo(perm_key('admin.company'))) {
            // مدير الشركة يمكنه تغيير الخزنة الافتراضية للمستخدمين في شركته
            if (!$authUser->company_id || ($user->company_id !== $authUser->company_id && !$user->companies->contains($authUser->company_id))) {
                return response()->json(['error' => 'Forbidden', 'message' => 'You do not have permission to change the cash box for this user.'], 403);
            }
        } elseif ($authUser->id === $user->id) {
            // المستخدم يمكنه تغيير الخزنة الافتراضية الخاصة به فقط
        } else {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to set the default cash box for this user.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // التأكد من أن الخزنة المراد تعيينها تابعة للمستخدم المستهدف
            $cashBox = CashBox::where('user_id', $user->id)
                ->where('id', $cashBoxId)
                ->first();

            if (!$cashBox) {
                DB::rollBack();
                return response()->json(['error' => 'Not Found', 'message' => 'Cash box not found or does not belong to this user.'], 404);
            }

            // إعادة تعيين جميع الصناديق النقدية لهذا المستخدم إلى is_default = false
            $user->cashBoxes()->update(['is_default' => false]);

            // تعيين الخزنة المحددة كخزنة افتراضية
            $cashBox->update(['is_default' => true]);

            $user->logUpdated('بتعيين الخزنة الافتراضية ' . $cashBox->name . ' للمستخدم ' . $user->nickname);
            DB::commit();

            return response()->json(['message' => 'Default cash box set successfully'], 200);
        } catch (Throwable $e) {
            DB::rollback();
            Log::error('Set default cash box failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $authUser ? $authUser->id : null,
            ]);
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تعيين الخزنة الافتراضية.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => app()->isLocal() ? $e->getTrace() : null,
                'user_id' => $authUser?->id,
            ], 500);
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
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
        ]);
        $user->company_id = $request->input('company_id');
        $user->save();
        return response()->json(['message' => 'تم تغيير الشركة بنجاح', 'user' => $user]);
    }
}

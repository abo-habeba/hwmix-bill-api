<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Roles\RoleResource;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class RoleController extends Controller
{
    /**
     * العلاقات الافتراضية المستخدمة مع الأدوار.
     * @var array
     */
    protected array $relations = [
        'permissions',
        'companies',
        'creator'
    ];

    /**
     * عرض قائمة بالأدوار.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = Role::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            // فلترة الأدوار حسب الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الأدوار بدون أي قيود
            } elseif ($authUser->hasAnyPermission([perm_key('roles.view_all'), perm_key('admin.company')])) {
                if (!$companyId) {
                    return api_unauthorized('المستخدم غير مرتبط بشركة.');
                }
                $query->whereHas('companies', function ($q) use ($companyId) {
                    $q->where('companies.id', $companyId);
                });
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_children'))) {
                if (!$companyId) {
                    return api_unauthorized('المستخدم غير مرتبط بشركة.');
                }
                $descendantUserIds = $authUser->getDescendantUserIds();
                $descendantUserIds[] = $authUser->id;
                $query->whereHas('companies', function ($q) use ($companyId, $descendantUserIds) {
                    $q
                        ->where('companies.id', $companyId)
                        ->whereIn('role_company.created_by', $descendantUserIds);
                });
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_self'))) {
                if (!$companyId) {
                    return api_unauthorized('المستخدم غير مرتبط بشركة.');
                }
                $query->whereHas('companies', function ($q) use ($companyId, $authUser) {
                    $q
                        ->where('companies.id', $companyId)
                        ->where('role_company.created_by', $authUser->id);
                });
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض الأدوار.');
            }

            if ($request->filled('company_id')) {
                // إذا تم تحديد company_id في الطلب، تأكد من أن المستخدم لديه صلاحية رؤية الأدوار لتلك الشركة
                if ($request->input('company_id') != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return api_forbidden('ليس لديك إذن لعرض الأدوار لشركة أخرى.');
                }
                $query->whereHas('companies', function ($q) use ($request) {
                    $q->where('companies.id', $request->input('company_id'));
                });
            }
            if ($request->filled('role_id')) {
                $query->where('id', $request->input('role_id'));
            }
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->input('name') . '%');
            }

            $perPage = (int) $request->input('per_page', 10);
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query->orderBy($sortField, $sortOrder);

            if ($perPage == -1) {
                // هات كل النتائج بدون باجينيشن
                $roles = $query->get();
            } else {
                // هات النتائج بباجينيشن
                $roles = $query->paginate(max(1, $perPage));
            }
            if ($roles->isEmpty()) {
                return api_success(RoleResource::collection($roles), 'لم يتم العثور على أدوار.');
            } else {
                return api_success(RoleResource::collection($roles), 'تم جلب الأدوار بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين دور جديد في قاعدة البيانات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasAnyPermission([
                perm_key('admin.super'),
                perm_key('admin.company'),
                perm_key('roles.create'),
            ])) {
                return api_forbidden('ليس لديك صلاحية لإنشاء الأدوار.');
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
                'company_ids' => ['nullable', 'array'],
                'company_ids.*' => ['exists:companies,id'],
                'permissions' => ['sometimes', 'array'],
                'permissions.*' => ['exists:permissions,name'],
            ]);

            if ($validator->fails()) {
                return api_error('فشل التحقق من صحة البيانات.', $validator->errors()->toArray(), 422);
            }

            $validatedData = $validator->validated();
            $validatedData['company_ids'] = $validatedData['company_ids'] ?? [$companyId];

            // تحقق من أن الشركات المحددة تنتمي للمستخدم الحالي أو أن المستخدم super_admin
            $allowedCompanyIds = [];
            foreach ($validatedData['company_ids'] as $id) {
                if ($id == $companyId || $authUser->hasPermissionTo(perm_key('admin.super'))) {
                    $allowedCompanyIds[] = $id;
                }
            }

            if (empty($allowedCompanyIds)) {
                return api_forbidden('لا يمكنك إنشاء أدوار إلا لشركتك النشطة ما لم تكن مسؤولاً عامًا.');
            }

            DB::beginTransaction();
            try {
                $roleName = $validatedData['name'];
                $assignedCreatedBy = $authUser->id;

                $role = Role::firstOrCreate(
                    ['name' => $roleName],
                    [
                        'guard_name' => 'web',
                        'created_by' => $assignedCreatedBy,
                        // لا نضع company_id هنا لأن الدور يمكن أن يكون مرتبطًا بعدة شركات عبر جدول pivot
                    ]
                );

                $pivotData = [];
                foreach ($allowedCompanyIds as $comp_id) {
                    $pivotData[$comp_id] = ['created_by' => $assignedCreatedBy];
                }

                $role->companies()->syncWithoutDetaching($pivotData);

                if (!empty($validatedData['permissions'])) {
                    $role->syncPermissions($validatedData['permissions']);
                }

                DB::commit();

                return api_success(new RoleResource($role->load($this->relations)), 'تم إنشاء الدور بنجاح.', 201);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء إنشاء الدور.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث الدور المحدد في قاعدة البيانات.
     *
     * @param Request $request
     * @param Role $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // تحميل العلاقات اللازمة للتحقق من الصلاحيات
            $role->load(['companies' => function ($q) use ($companyId) {
                $q->where('companies.id', $companyId)->withPivot('created_by');
            }, 'creator']);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasPermissionTo(perm_key('roles.update_all'))) {
                $canUpdate = $role->companies->isNotEmpty(); // الدور مرتبط بالشركة الحالية
            } elseif ($authUser->hasPermissionTo(perm_key('roles.update_children'))) {
                $descendantUserIds = $authUser->getDescendantUserIds();
                $descendantUserIds[] = $authUser->id;
                $canUpdate = $role->companies->contains(function ($company) use ($authUser, $descendantUserIds) {
                    return $company->pivot->created_by === $authUser->id || in_array($company->pivot->created_by, $descendantUserIds);
                });
            } elseif ($authUser->hasPermissionTo(perm_key('roles.update_self'))) {
                $canUpdate = $role->companies->contains(function ($company) use ($authUser) {
                    return $company->pivot->created_by === $authUser->id;
                });
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك صلاحية لتحديث هذا الدور.');
            }

            $validator = Validator::make($request->all(), [
                'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,' . $role->id],
                'company_ids' => ['sometimes', 'array'],
                'company_ids.*' => ['exists:companies,id'],
                'permissions' => ['sometimes', 'array'],
                'permissions.*' => ['exists:permissions,name'],
            ]);

            if ($validator->fails()) {
                return api_error('فشل التحقق من صحة البيانات.', $validator->errors()->toArray(), 422);
            }

            $validatedData = $validator->validated();
            $validatedData['updated_by'] = $authUser->id; // تعيين من قام بالتعديل

            DB::beginTransaction();
            try {
                if (isset($validatedData['name']) && $validatedData['name'] !== $role->name) {
                    $role->update(['name' => $validatedData['name']]);
                }

                if (isset($validatedData['company_ids'])) {
                    $newCompanyIds = [];
                    foreach ($validatedData['company_ids'] as $comp_id) {
                        // السماح بتغيير company_ids فقط إذا كان المستخدم super_admin
                        // أو إذا كانت الشركة الجديدة هي نفس الشركة الحالية للمستخدم
                        if ($comp_id == $companyId || $authUser->hasPermissionTo(perm_key('admin.super'))) {
                            $newCompanyIds[] = $comp_id;
                        } else {
                            DB::rollBack();
                            return api_forbidden('لا يمكنك ربط الدور بشركة أخرى ما لم تكن مسؤولاً عامًا.');
                        }
                    }

                    $pivotData = [];
                    foreach ($newCompanyIds as $comp_id) {
                        $pivotData[$comp_id] = ['created_by' => $authUser->id]; // من قام بربط الدور بالشركة
                    }
                    $role->companies()->sync($pivotData);
                }

                if (isset($validatedData['permissions']) && is_array($validatedData['permissions'])) {
                    $role->syncPermissions($validatedData['permissions']);
                }

                DB::commit();
                return api_success(new RoleResource($role->load($this->relations)), 'تم تحديث الدور بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الدور.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض الدور المحدد.
     *
     * @param Role $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Role $role): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // تحميل العلاقات اللازمة للتحقق من الصلاحيات
            $role->load(['companies' => function ($q) use ($companyId) {
                $q->where('companies.id', $companyId)->withPivot('created_by');
            }, 'creator']);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_all'))) {
                $canView = $role->companies->isNotEmpty(); // الدور مرتبط بالشركة الحالية
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_children'))) {
                $descendantUserIds = $authUser->getDescendantUserIds();
                $descendantUserIds[] = $authUser->id;
                $canView = $role->companies->contains(function ($company) use ($authUser, $descendantUserIds) {
                    return $company->pivot->created_by === $authUser->id || in_array($company->pivot->created_by, $descendantUserIds);
                });
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_self'))) {
                $canView = $role->companies->contains(function ($company) use ($authUser) {
                    return $company->pivot->created_by === $authUser->id;
                });
            }

            if ($canView) {
                return api_success(new RoleResource($role->load($this->relations)), 'تم استرداد الدور بنجاح.');
            }

            return api_forbidden('ليس لديك صلاحية لعرض هذا الدور.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف الأدوار المحددة من قاعدة البيانات.
     * سيؤدي هذا الإجراء إلى حذف سجل الدور الأساسي ويتسلسل لحذف جميع سجلات Pivot المرتبطة به في 'role_company'.
     * لذلك، هناك حاجة إلى تفويض دقيق.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $roleIds = $request->input('item_ids');
            if (!is_array($roleIds) || empty($roleIds)) {
                return api_error('معرفات الدور غير صالحة.', [], 400);
            }

            DB::beginTransaction();
            try {
                $deletedRoles = collect();
                foreach ($roleIds as $roleId) {
                    $role = Role::with(['companies' => function ($q) use ($companyId) {
                        $q->where('companies.id', $companyId)->withPivot('created_by');
                    }, 'users'])->find($roleId);

                    if (!$role) {
                        // إذا لم يتم العثور على الدور، تجاهله وانتقل إلى التالي
                        continue;
                    }

                    $canDelete = false;
                    if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                        $canDelete = true;
                    } elseif ($authUser->hasPermissionTo(perm_key('roles.delete_all'))) {
                        $canDelete = $role->companies->isNotEmpty(); // الدور مرتبط بالشركة الحالية
                    } elseif ($authUser->hasPermissionTo(perm_key('roles.delete_children'))) {
                        $descendantUserIds = $authUser->getDescendantUserIds();
                        $descendantUserIds[] = $authUser->id;
                        $canDelete = $role->companies->contains(function ($company) use ($authUser, $descendantUserIds) {
                            return $company->pivot->created_by === $authUser->id || in_array($company->pivot->created_by, $descendantUserIds);
                        });
                    } elseif ($authUser->hasPermissionTo(perm_key('roles.delete_self'))) {
                        $canDelete = $role->companies->contains(function ($company) use ($authUser) {
                            return $company->pivot->created_by === $authUser->id;
                        });
                    }

                    if (!$canDelete) {
                        DB::rollBack();
                        return api_forbidden('ليس لديك صلاحية لحذف الدور: ' . $role->name . ' (المعرف: ' . $role->id . ').');
                    }

                    // التحقق مما إذا كان الدور مرتبطًا بأي مستخدمين
                    if ($role->users()->exists()) {
                        DB::rollBack();
                        return api_error('لا يمكن حذف الدور "' . $role->name . '". إنه مرتبط بمستخدم واحد أو أكثر.', [], 409);
                    }

                    $replicatedRole = $role->replicate(); // نسخ الكائن قبل الحذف
                    $replicatedRole->setRelations($role->getRelations()); // نسخ العلاقات المحملة

                    $role->companies()->detach(); // فصل الدور عن الشركات المرتبطة به
                    $role->delete(); // حذف الدور نفسه
                    $deletedRoles->push($replicatedRole);
                }

                DB::commit();
                return api_success(RoleResource::collection($deletedRoles), 'تم حذف الأدوار بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف الأدوار.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تعيين أدوار متعددة لمستخدم.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRole(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasAnyPermission([
                perm_key('admin.super'),
                perm_key('admin.company'),
                perm_key('roles.assign'),
            ])) {
                return api_forbidden('ليس لديك صلاحية لتعيين الأدوار.');
            }

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'roles' => 'required|array|min:1',
                'roles.*' => 'required|string|exists:roles,name',
            ]);

            if ($validator->fails()) {
                return api_error('فشل التحقق من صحة البيانات.', $validator->errors()->toArray(), 422);
            }

            $validatedData = $validator->validated();
            $user = User::with('roles')->findOrFail($validatedData['user_id']);

            // التحقق من صلاحية تعيين الأدوار:
            // 1. لا يمكن للمستخدمين العاديين تعيين أدوار لمستخدمين خارج شركتهم.
            // 2. لا يمكن للمستخدمين العاديين تعيين أدوار لا يملكون صلاحية الوصول إليها.
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                // التحقق من أن المستخدم الذي يتم تعيين الأدوار له ينتمي لنفس الشركة
                if ($user->company_id !== $companyId) {
                    return api_forbidden('لا يمكنك تعيين أدوار لمستخدمين خارج شركتك.');
                }

                // التحقق من أن الأدوار التي يتم تعيينها متاحة للمستخدم الحالي
                $requestedRoleNames = $validatedData['roles'];
                $availableRoles = Role::whereHas('companies', function ($q) use ($companyId) {
                    $q->where('companies.id', $companyId);
                })->whereIn('name', $requestedRoleNames)->pluck('name')->toArray();

                $diff = array_diff($requestedRoleNames, $availableRoles);
                if (!empty($diff)) {
                    return api_forbidden('بعض الأدوار المطلوبة غير متاحة لك لتعيينها: ' . implode(', ', $diff));
                }
            }

            DB::beginTransaction();
            try {
                $user->syncRoles($validatedData['roles']); // تعيين جميع الأدوار دفعة واحدة
                DB::commit();
                return api_success([], 'تم تعيين الأدوار للمستخدم بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تعيين الأدوار للمستخدم.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

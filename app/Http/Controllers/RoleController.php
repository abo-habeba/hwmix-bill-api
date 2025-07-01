<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Roles\RoleResource;
use App\Models\Company;  // تأكد من استيراد نموذج الشركة
use App\Models\Role;
use App\Models\RoleCompany;  // لا يزال مفيدًا للتفاعل المباشر مع جدول Pivot إذا لزم الأمر
use App\Models\User;  // تأكد من استيراد نموذج المستخدم
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

// // دالة مساعدة لضمان الاتساق في مفاتيح الأذونات
// if (!function_exists('perm_key')) {
//     function perm_key(string $permission): string
//     {
//         return $permission;
//     }
// }

class RoleController extends Controller
{
    /**
     * عرض قائمة بالأدوار.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();
            $query = Role::query();

            // فلترة الأدوار حسب الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الأدوار بدون أي قيود
            } elseif ($authUser->hasAnyPermission([perm_key('roles.view_all'), perm_key('admin.company')])) {
                $companyId = $authUser->company_id;
                $query->whereHas('companies', function ($q) use ($companyId) {
                    $q->where('companies.id', $companyId);
                });
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_children'))) {
                $companyId = $authUser->company_id;
                $descendantUserIds = $authUser->getDescendantUserIds();
                $descendantUserIds[] = $authUser->id;
                $query->whereHas('companies', function ($q) use ($companyId, $descendantUserIds) {
                    $q
                        ->where('companies.id', $companyId)
                        ->whereIn('role_company.created_by', $descendantUserIds);
                });
            } elseif ($authUser->hasPermissionTo(perm_key('roles.view_self'))) {
                $companyId = $authUser->company_id;
                $query->whereHas('companies', function ($q) use ($companyId, $authUser) {
                    $q
                        ->where('companies.id', $companyId)
                        ->where('role_company.created_by', $authUser->id);
                });
            } else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            if ($request->filled('company_id')) {
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

            $perPage = max(1, $request->input('per_page', 10));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $roles = $query
                ->with([
                    'permissions',
                    'companies',
                    'creator'
                ])
                ->orderBy($sortField, $sortOrder)
                ->paginate($perPage);

            return RoleResource::collection($roles)->additional([
                'total' => $roles->total(),
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Role index failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'Error retrieving roles.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * تخزين دور جديد في قاعدة البيانات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Roles\RoleResource
     */
    public function store(Request $request)
    {
        $authUser = Auth::user();
        $companyId = $authUser->company_id;

        if (!$authUser->hasAnyPermission([
            perm_key('admin.super'),
            perm_key('admin.company'),
            perm_key('roles.create'),
        ])) {
            return response()->json(['error' => 'Unauthorized to create roles.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['exists:companies,id'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        $validatedData['company_ids'] = $validatedData['company_ids'] ?? [$authUser->company_id];

        // تحقق إن الشركة من الشركات المصرح بيها
        $companyIds = array_filter($validatedData['company_ids'], fn($id) => $id == $companyId);
        if (empty($companyIds)) {
            return response()->json(['error' => 'You can only create roles for your active company.'], 403);
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
                    'company_id' => $companyId,
                ]
            );

            $pivotData = [];
            foreach ($companyIds as $companyId) {
                $pivotData[$companyId] = ['created_by' => $assignedCreatedBy];
            }

            $role->companies()->syncWithoutDetaching($pivotData);

            if (!empty($validatedData['permissions'])) {
                $role->syncPermissions($validatedData['permissions']);
            }

            Log::info('Role created: ' . $role->name, ['role_id' => $role->id, 'user_id' => $authUser->id]);
            DB::commit();

            return new RoleResource($role->load('permissions', 'companies', 'creator'));
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Role store failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Error creating role.', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * تحديث الدور المحدد في قاعدة البيانات.
     *
     * @param Request $request
     * @param Role $role
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Roles\RoleResource
     */
    public function update(Request $request, Role $role)
    {
        $authUser = Auth::user();
        $companyId = $authUser->company_id;
        $canUpdate = false;
        if ($authUser->hasPermissionTo(perm_key('admin.super')) || $authUser->hasPermissionTo(perm_key('roles.update_all'))) {
            $canUpdate = true;
        } elseif ($authUser->hasPermissionTo(perm_key('roles.update_children'))) {
            $descendantUserIds = $authUser->getDescendantUserIds();
            $descendantUserIds[] = $authUser->id;
            $canUpdate = $role->companies()->where('companies.id', $companyId)->wherePivotIn('created_by', $descendantUserIds)->exists();
        } elseif ($authUser->hasPermissionTo(perm_key('roles.update_self'))) {
            $canUpdate = $role->companies()->where('companies.id', $companyId)->wherePivot('created_by', $authUser->id)->exists();
        }
        if (!$canUpdate) {
            return response()->json(['error' => 'Unauthorized to update this role.'], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,' . $role->id],
            'company_ids' => ['sometimes', 'array'],
            'company_ids.*' => ['exists:companies,id'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $validatedData = $validator->validated();
        DB::beginTransaction();
        try {
            if (isset($validatedData['name']) && $validatedData['name'] !== $role->name) {
                $role->update(['name' => $validatedData['name']]);
            }
            if (isset($validatedData['company_ids'])) {
                $newCompanyIds = array_filter((array) $validatedData['company_ids'], fn($id) => $id == $companyId);
                $pivotData = [];
                foreach ($newCompanyIds as $companyId) {
                    $pivotData[$companyId] = ['created_by' => $authUser->id];
                }
                $role->companies()->sync($pivotData);
            }
            if (isset($validatedData['permissions']) && is_array($validatedData['permissions'])) {
                $role->syncPermissions($validatedData['permissions']);
            }
            Log::info('Role updated: ' . $role->name, ['role_id' => $role->id, 'user_id' => $authUser->id]);
            DB::commit();
            return new RoleResource($role->load('permissions', 'companies', 'creator'));
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error updating role.', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * عرض الدور المحدد.
     *
     * @param Role $role
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Roles\RoleResource
     */
    public function show(Role $role)
    {
        $authUser = Auth::user();
        $companyId = $authUser->company_id;
        $canView = false;
        if ($authUser->hasPermissionTo(perm_key('admin.super')) || $authUser->hasPermissionTo(perm_key('roles.view_all'))) {
            $canView = $role->companies()->where('companies.id', $companyId)->exists();
        } elseif ($authUser->hasPermissionTo(perm_key('roles.view_children'))) {
            $descendantUserIds = $authUser->getDescendantUserIds();
            $descendantUserIds[] = $authUser->id;
            $canView = $role->companies()->where('companies.id', $companyId)->wherePivotIn('created_by', $descendantUserIds)->exists();
        } elseif ($authUser->hasPermissionTo(perm_key('roles.view_self'))) {
            $canView = $role->companies()->where('companies.id', $companyId)->wherePivot('created_by', $authUser->id)->exists();
        }
        if ($canView) {
            return new RoleResource($role->load('permissions', 'companies', 'creator'));
        }
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    /**
     * حذف الأدوار المحددة من قاعدة البيانات.
     * سيؤدي هذا الإجراء إلى حذف سجل الدور الأساسي ويتسلسل لحذف جميع سجلات Pivot المرتبطة به في 'role_company'.
     * لذلك، هناك حاجة إلى تفويض دقيق.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $authUser = Auth::user();
        $companyId = $authUser->company_id;
        $roleIds = $request->input('item_ids');
        if (!is_array($roleIds) || empty($roleIds)) {
            return response()->json(['error' => 'Invalid role IDs provided.'], 400);
        }
        DB::beginTransaction();
        try {
            $rolesToDelete = Role::whereIn('id', $roleIds)->get();
            $deletedRoleNames = [];
            foreach ($rolesToDelete as $role) {
                $canDelete = false;
                if ($authUser->hasPermissionTo(perm_key('admin.super')) || $authUser->hasPermissionTo(perm_key('roles.delete_all'))) {
                    $canDelete = $role->companies()->where('companies.id', $companyId)->exists();
                } elseif ($authUser->hasPermissionTo(perm_key('roles.delete_children'))) {
                    $descendantUserIds = $authUser->getDescendantUserIds();
                    $descendantUserIds[] = $authUser->id;
                    $canDelete = $role->companies()->where('companies.id', $companyId)->wherePivotIn('created_by', $descendantUserIds)->exists();
                } elseif ($authUser->hasPermissionTo(perm_key('roles.delete_self'))) {
                    $canDelete = $role->companies()->where('companies.id', $companyId)->wherePivot('created_by', $authUser->id)->exists();
                }
                if (!$canDelete) {
                    DB::rollBack();
                    return response()->json(['error' => 'Unauthorized to delete role: ' . $role->name . ' (ID: ' . $role->id . ').'], 403);
                }
                $role->companies()->detach();
                $role->delete();
                $deletedRoleNames[] = $role->name;
                Log::info('Role deleted: ' . $role->name, ['role_id' => $role->id, 'user_id' => $authUser->id]);
            }
            DB::commit();
            return response()->json(['message' => 'Roles deleted successfully: ' . implode(', ', $deletedRoleNames)], 200);
        } catch (Throwable $e) {
            DB::rollback();
            Log::error('Role deletion failed: ' . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser?->id]);
            return response()->json(['error' => 'Error deleting roles.', 'details' => $e->getMessage()], 500);
        }
    }
}

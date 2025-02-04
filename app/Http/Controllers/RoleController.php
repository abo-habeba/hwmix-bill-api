<?php
namespace App\Http\Controllers;

use App\Models\RoleCompany;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Resources\Roles\RoleResource;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\Roles\RolesResource;

class RoleController extends Controller
{
    private $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }
    //   { value: 'roles_all', name: ' جميع الأدوار' },
    //   { value: 'roles_all_own', name: 'الأدوار التابعة له' },
    //   { value: 'roles_all_self', name: 'عرض الأدوار الخاصة به ' },

    //   { value: 'roles_show', name: 'عرض تفاصيل أي دور' },
    //   { value: 'roles_show_own', name: ' تفاصيل الأدوار التابعة له' },
    //   { value: 'roles_show_self', name: ' تفاصيل الأدوار الخاصه به ' },

    //   { value: 'roles_create', name: 'إنشاء دور' },

    //   { value: 'roles_update', name: 'تعديل أي دور' },
    //   { value: 'roles_update_own', name: 'تعديل الأدوار التابعة له' },
    //   { value: 'roles_update_self', name: 'تعديل الأدوار الخاصه به' },

    //   { value: 'roles_delete', name: 'حذف أي دور' },
    //   { value: 'roles_delete_own', name: 'حذف الأدوار التابعة له' },
    //   { value: 'roles_delete_self', name: 'حذف الأدوار الخاصه به' },

    public function index()
    {
        $authUser = auth()->user();
        // $rolesQuery = Role::query();
        $rolesQuery = RoleCompany::query();
        if ($authUser->hasAnyPermission(['roles_all', 'company_owner', 'super_admin'])) {
            $rolesQuery->company();
        } elseif ($authUser->hasPermissionTo('roles_all_own')) {
            $rolesQuery->own()->company();
        } elseif ($authUser->hasPermissionTo('roles_all_self')) {
            $rolesQuery->self()->company();
        } else {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
        }

        $roles = $rolesQuery->with(['role.permissions', 'company', 'createdBy'])->get();
        return RolesResource::collection($roles);
    }
    public function store(Request $request)
    {

        $authUser = auth()->user();
        if ($authUser->hasAnyPermission(['super_admin', 'roles_create', 'company_owner'])) {
            $validated = $request->validate([
                'name' => 'required',
                'guard_name' => 'nullable',
                'created_by' => 'nullable',
                'permissions' => 'nullable',
            ]);

            $validated['guard_name'] = $validated['guard_name'] ?? 'web';
            $validated['created_by'] = $validated['created_by'] ?? $authUser->id;
            $validated['company_id'] = $validated['company_id'] ?? $authUser->company_id;

            try {
                DB::beginTransaction();
                $role = Role::create($validated);

                // $role->companies()->attach($validated['company_id'], [
                //     'created_by' => $validated['created_by'],
                // ]);

                RoleCompany::create([
                    'role_id' => $role->id,
                    'company_id' => $validated['company_id'],
                    'created_by' => $validated['created_by'],
                ]);

                if (!empty($validated['permissions'])) {
                    $role->syncPermissions($validated['permissions']);
                }
                DB::commit();

                return response()->json(new RoleResource($role), 201);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        }

        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }
    public function show(Role $role)
    {
        $authUser = auth()->user();

        if ($authUser->hasPermissionTo('company_owner')) {
            if ($role->isCompany()) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this role.'], 403);
            }
        } elseif ($authUser->hasPermissionTo('roles_show_own')) {
            if ($role->isOwn()) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this role.'], 403);
            }
        } elseif ($authUser->hasPermissionTo('roles_show_self')) {
            if ($role->isٍٍٍSelf()) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this role.'], 403);
            }
        } else {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
        }
        $role->load('permissions');

        return response()->json($role);
    }

    public function update(Request $request, Role $role)
    {
        $authUser = auth()->user();

        $validated = $request->validate([
            'name' => "required|unique:roles,name,{$role->id}",
            'guard_name' => 'nullable',
            'created_by' => 'nullable',
            'permissions' => 'nullable',
        ]);
        $validated['guard_name'] = $validated['guard_name'] ?? 'web';
        $validated['created_by'] = $validated['created_by'] ?? $authUser->id;
        if (
            $authUser->hasAnyPermission(['super_admin', 'roles_update']) ||
            ($authUser->hasPermissionTo('company_owner') && $role->isCompany()) ||
            ($authUser->hasPermissionTo('roles_update_own') && $role->isOwn()) ||
            ($authUser->hasPermissionTo('roles_update_self') && $role->isٍٍٍSelf())
        ) {
            try {
                DB::beginTransaction();
                $role->update($validated);
                if (!empty($validated['permissions'])) {
                    $role->syncPermissions($validated['permissions']);
                }
                DB::commit();
                return response()->json($role, 201);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        }
        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }
    public function destroy(Role $role)
    {
        $authUser = auth()->user();

        // تحقق من الأذونات بناءً على المعايير المحددة
        if (
            $authUser->hasAnyPermission(['super_admin', 'roles_delete']) ||
            $authUser->hasPermissionTo('company_owner') ||
            $authUser->hasPermissionTo('roles_delete_own') ||
            $authUser->hasPermissionTo('roles_delete_self')
        ) {
            try {
                DB::beginTransaction();

                $role->delete();
                // $role->logForceDeleted(' الدور ' . $role->name);
                DB::commit();
                return response()->json(['message' => 'Role deleted successfully'], 200);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        }
        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }
    public function assignRole(Request $request)
    {
        try {
            DB::beginTransaction();
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'roles' => 'nullable|array',
                'roles_*' => 'nullable|exists:roles,name',
            ]);
            $user = \App\Models\User::findOrFail($validated['user_id']);
            $user->syncRoles($validated['roles']);
            $user->logCreated(' اسناد الادوار [' . implode(' - ', $validated['roles']) . "]   الي المستخدم {$user->nickname}");
            DB::commit();
            return response()->json(new UserResource($user));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $th->getMessage(),
            ], 500);
        }

    }
}

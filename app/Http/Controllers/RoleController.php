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
    //   { value: 'roles.all', name: ' جميع الأدوار' },
    //   { value: 'roles.all.own', name: 'الأدوار التابعة له' },
    //   { value: 'roles.all.self', name: 'عرض الأدوار الخاصة به ' },

    //   { value: 'roles.show', name: 'عرض تفاصيل أي دور' },
    //   { value: 'roles.show.own', name: ' تفاصيل الأدوار التابعة له' },
    //   { value: 'roles.show.self', name: ' تفاصيل الأدوار الخاصه به ' },

    //   { value: 'roles.create', name: 'إنشاء دور' },

    //   { value: 'roles.update', name: 'تعديل أي دور' },
    //   { value: 'roles.update.own', name: 'تعديل الأدوار التابعة له' },
    //   { value: 'roles.update.self', name: 'تعديل الأدوار الخاصه به' },

    //   { value: 'roles.delete', name: 'حذف أي دور' },
    //   { value: 'roles.delete.own', name: 'حذف الأدوار التابعة له' },
    //   { value: 'roles.delete.self', name: 'حذف الأدوار الخاصه به' },

    public function index()
    {
        $authUser = auth()->user();
        // $rolesQuery = Role::query();
        $rolesQuery = RoleCompany::query();
        if ($authUser->hasAnyPermission(['roles.all', 'company.owner', 'super.admin'])) {
            $rolesQuery->company();
        } elseif ($authUser->hasPermissionTo('roles.all.own')) {
            $rolesQuery->own()->company();
        } elseif ($authUser->hasPermissionTo('roles.all.self')) {
            $rolesQuery->self()->company();
        } else {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
        }

        $roles = $rolesQuery->with(['role.permissions', 'company', 'createdBy'])->get();

        // $roles = Role::with('permissions')->get();

        // $roles = $rolesQuery->with(['company', 'createdBy'])->get();
        // $roles->load('role.permissions');

        // $roles = $rolesQuery->with(['role.permissions', 'company', 'createdBy'])->get()->load('role.permissions');


        // return $roles;
        return RolesResource::collection($roles);
    }
    public function store(Request $request)
    {

        $authUser = auth()->user();
        if ($authUser->hasAnyPermission(['super.admin', 'roles.create', 'company.owner'])) {
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

        if ($authUser->hasPermissionTo('company.owner')) {
            if ($role->isCompany()) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this role.'], 403);
            }
        } elseif ($authUser->hasPermissionTo('roles.show.own')) {
            if ($role->isOwn()) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this role.'], 403);
            }
        } elseif ($authUser->hasPermissionTo('roles.show.self')) {
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
            $authUser->hasAnyPermission(['super.admin', 'roles.update']) ||
            ($authUser->hasPermissionTo('company.owner') && $role->isCompany()) ||
            ($authUser->hasPermissionTo('roles.update.own') && $role->isOwn()) ||
            ($authUser->hasPermissionTo('roles.update.self') && $role->isٍٍٍSelf())
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
            $authUser->hasAnyPermission(['super.admin', 'roles.delete']) ||
            $authUser->hasPermissionTo('company.owner') ||
            $authUser->hasPermissionTo('roles.delete.own') ||
            $authUser->hasPermissionTo('roles.delete.self')
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
                'roles.*' => 'nullable|exists:roles,name',
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

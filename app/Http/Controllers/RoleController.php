<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;

class RoleController extends Controller
{
    // permissions: [
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
    // ],
    public function index()
    {
        $authUser = auth()->user();
        $rolesQuery = Role::query();

        if ($authUser->hasAnyPermission(['roles.all', 'super.admin'])) {
            //
        } elseif ($authUser->hasPermissionTo('company.owner')) {
            $rolesQuery->companyOwn();
        } elseif ($authUser->hasPermissionTo('roles.all.own')) {
            $rolesQuery->own();
        } elseif ($authUser->hasPermissionTo('roles.all.self')) {
            $rolesQuery->where('created_by', $authUser->id);
        } else {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
        }

        $roles = $rolesQuery->with('permissions')->get();

        return RoleResource::collection($roles);
    }


    public function store(Request $request)
    {
        $authUser = auth()->user();
        if ($authUser->hasAnyPermission(['super.admin', 'roles.create', 'company.owner'])) {
            $validated = $request->validate([
                'name' => 'required|unique:roles,name',
                'guard_name' => 'nullable',
                'created_by' => 'nullable',
                'permissions' => 'nullable',
            ]);

            $validated['guard_name'] = $validated['guard_name'] ?? 'web';

            $validated['created_by'] = $validated['created_by'] ?? $authUser->id;

            $role = Role::create($validated);
            if (!empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            return response()->json($role, 201);
        }

        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }


    public function show(Role $role)
    {
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
            ($authUser->hasPermissionTo('roles.update.own') && $role->isOwn()) ||
            ($authUser->hasPermissionTo('company.owner') && $authUser->company_id === $role->company_id) ||
            ($authUser->hasPermissionTo('roles.show.self') && $role->created_by == $authUser->id)
        ) {
            $role->update($validated);
            if (!empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }
            return response()->json($role, 201);
        }
        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }


    public function destroy(Role $role)
    {
        $authUser = auth()->user();

        // تحقق من الأذونات بناءً على المعايير المحددة
        if (
            $authUser->hasAnyPermission(['super.admin', 'roles.delete']) ||
            ($authUser->hasPermissionTo('roles.delete.own') && $role->isOwn()) ||
            ($authUser->hasPermissionTo('roles.delete.self') && $role->created_by == $authUser->id) ||
            ($authUser->hasPermissionTo('company.owner') && $authUser->company_id === $role->company_id)
        ) {
            // إلغاء تعيين الدور عن جميع المستخدمين الذين يمتلكونه
            $usersWithRole = $role->users;

            foreach ($usersWithRole as $user) {
                $user->removeRole($role->name);  // إلغاء تعيين الدور من المستخدم
            }

            // حذف الدور بعد إلغاء تعيينه
            $role->delete();

            return response()->json(['message' => 'Role deleted successfully'], 200);
        }

        // في حالة عدم وجود إذن للمستخدم
        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }



    public function assignRole(Request $request)
    {

        try {
            $validated = $request->validate([
                'roles' => 'required|array',
                'roles.*' => 'exists:roles,name',
                'user_id' => 'required|exists:users,id',
            ]);

            $user = \App\Models\User::findOrFail($validated['user_id']);

            // استخدام syncRoles لإزالة الأدوار الحالية وتعيين الأدوار الجديدة
            $user->syncRoles($validated['roles']);

            return response()->json(new UserResource($user));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $th->getMessage(),
            ], 500);
        }

    }
}

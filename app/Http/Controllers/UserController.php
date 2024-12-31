<?php

namespace App\Http\Controllers;


use App\Models\User;
use PHPUnit\Exception;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\UserUpdateRequest;
// use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    // use AuthorizesRequests;
    /**
     * Display a listing of users.
     */

    public function index(Request $request)
    {
        try {
            $authUser = auth()->user();
            $query = User::query();

            if ($authUser->hasAnyPermission(['users.all', 'super.admin'])) {
                // عرض جميع المستخدمين
            } elseif ($authUser->hasPermissionTo('company.owner')) {
                $query->companyOwn();
            } elseif ($authUser->hasPermissionTo('users.show.own')) {
                $query->own();
            } elseif ($authUser->hasPermissionTo('users.show.self')) {
                $query->where('created_by', $authUser->id);
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
            }

            $query->where('id', '<>', $authUser->id);

            if (!empty($request->get('nickname'))) {
                $query->where('nickname', 'like', '%' . $request->get('nickname') . '%');
            }

            if (!empty($request->get('email'))) {
                $query->where('email', 'like', '%' . $request->get('email') . '%');
            }

            if (!empty($request->get('status'))) {
                $query->where('status', $request->get('status'));
            }

            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }

            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');

            $query->orderBy($sortField, $sortOrder);

            // جلب البيانات مع التصفية والصفحات
            $users = $query->paginate($perPage);

            return response()->json([
                'data' => UserResource::collection($users->items()),
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    /**
     * Store a newly created user in storage.
     */
    public function store(UserRequest $request)
    {
        // Check if the authenticated user has the required permissions
        $authUser = auth()->user();

        if (!$authUser->hasAnyPermission(['super.admin', 'users.create', 'company.owner'])) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
        }

        // Validate the request data
        $validatedData = $request->validated();

        // Set default values for 'company_id' and 'created_by' if not provided
        $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
        $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;

        // Create the user
        $user = User::create($validatedData);

        // Return the created user as a resource
        return new UserResource($user);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $authUser = auth()->user(); // المستخدم الحالي

        // التحقق من الصلاحيات
        if (
            $authUser->hasPermissionTo('users.show') ||
            $authUser->hasPermissionTo('super.admin') ||
            ($authUser->hasPermissionTo('users.show.own') && $authUser->id === $user->id) ||
            ($authUser->hasPermissionTo('company.owner') && $authUser->company_id === $user->company_id) ||
            $authUser->id === $user->id
        ) {
            return new UserResource($user);
        }

        // إذا لم يمتلك الصلاحيات
        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        $authUser = auth()->user();

        $validated = $request->validated();
        if ($authUser->id === $user->id) {
            unset($validated['status'], $validated['balance']);
        }

        if (
            $authUser->hasAnyPermission(['super.admin', 'users.update']) ||
            ($authUser->hasPermissionTo('users.update.own') && $user->isOwn()) ||
            ($authUser->hasPermissionTo('company.owner') && $authUser->company_id === $user->company_id)
        ) {


            try {
                DB::beginTransaction();
                $user->update($validated);
                if (!empty($validated['permissions'])) {
                    $user->syncPermissions($validated['permissions']);
                }

                $user->logUpdated(' المستخدم  ' . $user->nickname);
                DB::commit();
                return new UserResource($user);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

            // $user->update($validated);

        }

        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request)
    {
        $authUser = auth()->user();

        $userIds = $request->input('user_ids');
        if (!$userIds || !is_array($userIds)) {
            return response()->json(['error' => 'Invalid user IDs provided'], 400);
        }
        $usersToDelete = User::whereIn('id', $userIds)->get();

        foreach ($usersToDelete as $user) {
            if (
                $authUser->hasAnyPermission(['super.admin', 'users.delete']) ||
                ($authUser->hasPermissionTo('users.delete.own') && $user->isOwn()) ||
                ($authUser->hasPermissionTo('users.delete.self') && $user->id === $authUser->id) ||
                ($authUser->hasPermissionTo('company.owner') && $authUser->company_id === $user->company_id)
            ) {
                continue;
            }

            return response()->json(['error' => 'You do not have permission to delete user with ID: ' . $user->id], 403);
        }

        foreach ($usersToDelete as $user) {
            $user->delete();
        }

        // User::whereIn('id', $userIds)->delete();


        return response()->json(['message' => 'Users deleted successfully'], 200);
    }

}

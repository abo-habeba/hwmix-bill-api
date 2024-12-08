<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
class UserController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        try {
            $this->authorize('viewAny', User::class);

            $authUser = auth()->user();
            $query = User::query();

            if ($authUser->hasPermissionTo('users.show')) {
                // عرض جميع المستخدمين
            } elseif ($authUser->hasPermissionTo('employee')) {
                $query->where('created_by', $authUser->id);
            } elseif ($authUser->type === 'company_owner') {
                $query->where('company_id', $authUser->company_id)->whereNotNull('company_id');
            } else {
                return response()->json(['code' => 403, 'message' => 'Unauthorized action.'], 403);
            }

            if ($request->has('name') && !empty($request->get('name'))) {
                $query->where('name', 'like', '%' . $request->get('name') . '%');
            }
            if ($request->has('email') && !empty($request->get('email'))) {
                $query->where('email', 'like', '%' . $request->get('email') . '%');
            }
            if ($request->has('status') && !empty($request->get('status'))) {
                $query->where('status', $request->get('status'));
            }
            if ($request->has('created_at_from')) {
                $createdAtFrom = $request->get('created_at_from');
                if ($createdAtFrom) {
                    $query->where('created_at', '>=', $createdAtFrom . ' 00:00:00');
                }
            }
            if ($request->has('created_at_to')) {
                $createdAtTo = $request->get('created_at_to');
                if ($createdAtTo) {
                    $query->where('created_at', '<=', $createdAtTo . ' 23:59:59');
                }
            }

            $perPage = max(1, $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');

            $query->orderBy($sortField, $sortOrder);

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
        // Authorize the action to create a user
        $this->authorize('create', User::class);

        // Validate the request data
        $validatedData = $request->validated();

        $user = User::create($validatedData);

        return new UserResource($user);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        // Authorize the action to view user details
        $this->authorize('viewOwn', $user);

        return new UserResource($user);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UserRequest $request, User $user)
    {
        // Authorize the action to update user details
        $this->authorize('update', $user);

        // Validate the request data
        $validatedData = $request->validated();

        $user->update($validatedData);

        return new UserResource($user);  // Return the updated user using UserResource
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        // Authorize the action to delete a user
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.'], 200);  // Return success message
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Http\Resources\User\UserResource;
use App\Models\Scopes\CompanyScope;
use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Exception;
// use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    // use AuthorizesRequests;

    /**
     * Display a listing of users.
     */
    private $relations;

    public function __construct()
    {
        $this->relations = [
            // 'trans',  // MorphMany: لديه ترجمات متعددة.
            'companies',  // BelongsToMany: مرتبط بالعديد من الشركات.
            // 'companyUsersCash',  // BelongsToMany: مرتبط بصناديق النقد للشركات (مع pivot).
            'cashBoxes',  // HasMany: لديه العديد من صناديق النقد.
            'cashBoxeDefault',  // HasOne: لديه صندوق نقد افتراضي واحد.
            // 'createdRoles',  // HasManyThrough: أنشأ العديد من الأدوار (عبر جدول وسيط).
            // 'installments',  // HasMany: أقساط العميل.
            // 'createdInstallments',  // HasMany: أقساط أنشأها.
            // 'transactions',  // HasMany: معاملات مالية.
            'creator',  // BelongsTo: تم إنشاؤه بواسطة.
            // 'invoices',  // HasMany: فواتير.
            // 'installmentPlans',  // HasMany: خطط أقساط.
            // 'payments',  // HasMany: مدفوعات.
        ];
    }

    public function index(Request $request)
    {
        try {
            $authUser = auth()->user();
            $query = User::with($this->relations);

            if ($authUser->hasAnyPermission(['users_all', 'super_admin'])) {
                // لا تضف أي scope → يرجع كل المستخدمين
            } elseif ($authUser->hasPermissionTo('company_owner')) {
                $query->company();
            } elseif ($authUser->hasPermissionTo('users_show_own')) {
                $query->own();
            } elseif ($authUser->hasPermissionTo('users_show_self')) {
                $query->self();
            } else {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to access this resource.'
                ], 403);
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
            $users = $query->with('companies')->paginate($perPage);

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

        if (!$authUser->hasAnyPermission(['super_admin', 'users_create', 'company_owner'])) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
        }

        // Validate the request data
        $validatedData = $request->validated();
        try {
            DB::beginTransaction();
            $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
            $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;

            $user = User::create($validatedData);
            $cashBoxType = CashBoxType::where('description', 'النوع الافتراضي للسيستم')->first();

            // throw new \Exception($cashBoxType->id);
            if ($cashBoxType) {
                // إنشاء خزنة للمستخدم
                CashBox::create([
                    'name' => 'نقدي',
                    'balance' => 0,
                    'cash_box_type_id' => $cashBoxType->id,
                    'is_default' => true,
                    'account_number' => $user->id,
                    'user_id' => $user->id,
                    'created_by' => $user->id,
                    'company_id' => $user->company_id,
                ]);
            } else {
                throw new \Exception('نوع الخزنة الافتراضي غير موجود.');
            }

            $user->companies()->sync($validatedData['company_ids']);
            $user->logCreated(' بانشاء  المستخدم ' . $user->nickname);
            DB::commit();
            return new UserResource($user);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $authUser = auth()->user();

        if (
            $authUser->hasPermissionTo('company_owner') ||
            $authUser->hasPermissionTo('users_show') ||
            $authUser->hasPermissionTo('super_admin') ||
            ($authUser->hasPermissionTo('users_show_own') && $authUser->id === $user->id) ||
            ($authUser->hasPermissionTo('company_owner') && $authUser->company_id === $user->company_id) ||
            $authUser->id === $user->id
        ) {
            // return $user->load('companies');
            return new UserResource($user->load('companies'));
        }

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
            $authUser->hasAnyPermission(['super_admin', 'users_update']) ||
            ($authUser->hasPermissionTo('company_owner') && $user->isCompany()) ||
            ($authUser->hasPermissionTo('users_update_own') && $user->isOwn()) ||
            ($authUser->hasPermissionTo('users_update_self') && $user->isSelf())
        ) {
            try {
                DB::beginTransaction();

                // تحديث كلمة المرور فقط إذا تم إرسالها
                if (!empty($validated['password'])) {
                    $user->password = $validated['password'];  // سيتم تشفيرها تلقائيًا
                }

                $user->update($validated);

                if (!empty($validated['permissions'])) {
                    $user->syncPermissions($validated['permissions']);
                }
                if (!empty($validated['company_ids'])) {
                    $user->companies()->sync($validated['company_ids']);
                }

                $user->logUpdated(' المستخدم  ' . $user->nickname);
                DB::commit();

                return new UserResource($user);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        }

        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request)
    {
        $authUser = auth()->user();

        $userIds = $request->input('item_ids');

        if (!$userIds || !is_array($userIds)) {
            return response()->json(['error' => 'Invalid user IDs provided'], 400);
        }
        $usersToDelete = User::whereIn('id', $userIds)->get();

        foreach ($usersToDelete as $user) {
            if (
                $authUser->hasAnyPermission(['super_admin', 'users_delete']) ||
                ($authUser->hasPermissionTo('users_delete_own') && $user->isOwn()) ||
                ($authUser->hasPermissionTo('users_delete_self') && $user->id === $authUser->id) ||
                ($authUser->hasPermissionTo('company_owner') && $authUser->company_id === $user->company_id)
            ) {
                continue;
            }

            return response()->json(['error' => 'You do not have permission to delete user with ID: ' . $user->id], 403);
        }
        try {
            DB::beginTransaction();
            foreach ($usersToDelete as $user) {
                $user->delete();
                $user->logForceDeleted(' المستخدم  ' . $user->nickname);
            }
            DB::commit();
            // User::whereIn('id', $userIds)->delete();
            return response()->json(['message' => 'Users deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function usersSearch(Request $request)
    {
        try {
            $authUser = auth()->user();
            $companyId = $authUser->company_id;
            $query = User::query();
            $query->where('id', '<>', $authUser->id);
            if (!empty($request->get('search'))) {
                $search = $request->get('search');
                if (strlen($search) < 4) {
                    $query->where('id', $search);
                } else {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('id', $search)
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    });
                }
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');

            $query->orderBy($sortField, $sortOrder);

            // جلب البيانات مع التصفية والصفحات
            $users = $query->with('companies')->paginate($perPage);

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

    public function setDefaultCashBox(User $user, $cashBoxId)
    {
        $cashBox = $user->cashBoxes()->where('id', $cashBoxId)->firstOrFail();
        $user->cashBoxes()->update(['is_default' => 0]);
        $cashBox->update(['is_default' => 1]);
        return response()->json(['message' => 'Default cash box updated successfully']);
    }

    public function changeCompany(Request $request, User $user)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',  // Ensure the company exists
        ]);
        $user->update([
            'company_id' => $request->company_id,
        ]);
        return response()->json([
            'message' => 'Company updated successfully.',
            'user' => $user,
        ], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Http\Resources\User\UserResource;
// use App\Models\Scopes\CompanyScope; // قد لا تحتاج لاستيراد الـ scopes إذا كانت معرفة كـ methods في الـ User model
use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;  // استخدام Log لتسجيل الأخطاء
use Throwable;  // استخدم Throwable للتعامل الشامل مع الأخطاء والاستثناءات

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
            // 'trans',
            // 'companyUsersCash',
            // 'createdRoles',
            // 'installments',
            // 'createdInstallments',
            // 'transactions',
            // 'invoices',
            // 'installmentPlans',
            // 'payments',
        ];
    }

    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        try {
            $authUser = auth()->user();

            if (!$authUser) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required.'
                ], 401);
            }

            $query = User::with($this->relations);

            // تطبيق منطق الصلاحيات
            if ($authUser->hasAnyPermission(['users_all', 'super_admin'])) {
                // إذا كان لديه صلاحية 'users_all' أو 'super_admin'، لا حاجة لتطبيق أي scope خاص
            } elseif ($authUser->hasPermissionTo('company_owner')) {
                // إذا كان مالك شركة، طبق scopeCompany لجلب مستخدمي شركته فقط
                $query->scopeCompany();
            } elseif ($authUser->hasPermissionTo('users_show_own')) {
                // إذا كان لديه صلاحية 'users_show_own'، طبق scopeOwn لجلب المستخدمين الذين أنشأهم هو
                $query->scopeOwn();
            } elseif ($authUser->hasPermissionTo('users_show_self')) {
                // إذا كان لديه صلاحية 'users_show_self'، طبق scopeSelf لجلب المستخدم الحالي فقط
                $query->scopeSelf();
            } else {
                // إذا لم يكن لديه أي صلاحية رؤية عامة، ارجع خطأ Unauthorized
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to view users.'
                ], 403);
            }

            // استبعاد المستخدم الحالي من القائمة إذا لم يكن super_admin
            // هذا يعتمد على سياق 'users_all_except_self' لو كانت موجودة
            // للتأكد من عدم عرض المستخدم لنفسه ضمن قائمة عامة (يمكن للمستخدم عرض ملفه الشخصي عبر show)
            if (!$authUser->hasAnyPermission(['super_admin', 'users_all'])) {
                $query->where('id', '<>', $authUser->id);
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

            $query->orderBy($sortField, $sortOrder);

            $users = $query->paginate($perPage);

            return UserResource::collection($users)->additional([
                'total' => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ]);
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
                'message' => 'حدث خطأ أثناء جلب المستخدمين.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => app()->isLocal() ? $e->getTrace() : null,  // لو انت بتطور محليًا فقط
                'user_id' => $authUser?->id,
            ], 500);
        }
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(UserRequest $request)
    {
        $authUser = auth()->user();

        if (!$authUser || !$authUser->hasAnyPermission(['super_admin', 'users_create', 'company_owner'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to create users.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $validatedData = $request->validated();

            // إذا كان super_admin يسمح بتحديد company_id أو created_by
            // وإلا، يتم تعيينها من المستخدم الحالي
            $validatedData['company_id'] = isset($validatedData['company_ids']) && is_array($validatedData['company_ids'])
                ? $validatedData['company_ids'][0]
                : ($validatedData['company_id'] ?? $authUser->company_id);

            $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;

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
                    'created_by' => $user->id,
                    'company_id' => $user->company_id,
                ]);
            } else {
                throw new \Exception('نوع الخزنة الافتراضي غير موجود.');
            }

            if (!empty($validatedData['company_ids'])) {
                $user->companies()->sync($validatedData['company_ids']);
            }

            $user->logCreated(' بانشاء المستخدم ' . $user->nickname);
            DB::commit();
            return new UserResource($user->load($this->relations));  // حمل العلاقات بعد الإنشاء
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
                'trace' => app()->isLocal() ? $e->getTrace() : null,  // لو انت بتطور محليًا فقط
                'user_id' => $authUser?->id,
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $authUser = auth()->user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        $query = User::where('id', $user->id)->with($this->relations);

        // تطبيق منطق الصلاحيات
        if ($authUser->hasAnyPermission(['users_show', 'users_all', 'super_admin'])) {
            // لا حاجة لـ scope خاص، يكفي أن المنتج موجود
        } elseif ($authUser->hasPermissionTo('company_owner')) {
            $query->scopeCompany();
        } elseif ($authUser->hasPermissionTo('users_show_own')) {
            $query->scopeOwn();
        } elseif ($authUser->hasPermissionTo('users_show_self')) {
            $query->scopeSelf();
        } else {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to view this user.'
            ], 403);
        }

        $authorizedUser = $query->first();

        if (!$authorizedUser) {
            // إذا لم يتم العثور على المستخدم بعد تطبيق الـ scopes، يعني المستخدم غير مصرح له برؤيته
            return response()->json([
                'error' => 'Not Found',
                'message' => 'User not found or you do not have permission to view it.'
            ], 404);
        }

        return new UserResource($authorizedUser);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        $authUser = auth()->user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        $query = User::where('id', $user->id);

        // تطبيق منطق الصلاحيات قبل التحديث
        if ($authUser->hasAnyPermission(['users_update', 'super_admin'])) {
            // لا حاجة لـ scope خاص
        } elseif ($authUser->hasPermissionTo('company_owner')) {
            $query->scopeCompany();
        } elseif ($authUser->hasPermissionTo('users_update_own')) {
            $query->scopeOwn();
        } elseif ($authUser->hasPermissionTo('users_update_self')) {
            $query->scopeSelf();
        } else {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to update this user.'
            ], 403);
        }

        $authorizedUser = $query->first();

        if (!$authorizedUser) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'User not found or you do not have permission to update it.'
            ], 404);
        }

        // تأكد أن المستخدم الذي يتم تحديثه هو نفسه الذي تم التحقق من صلاحيته
        $user = $authorizedUser;

        DB::beginTransaction();
        try {
            $validated = $request->validated();
            // return $validated;

            $validated['company_id'] = isset($validated['company_ids']) && is_array($validated['company_ids'])
                ? $validated['company_ids'][0]
                : ($validated['company_id'] ?? $authUser->company_id);

            $validated['created_by'] = $validated['created_by'] ?? $authUser->id;

            // منع المستخدم العادي من تغيير حالته أو رصيده
            if (!$authUser->hasAnyPermission(['super_admin', 'company_owner'])) {  // افترض أن super_admin و company_owner يمكنهما التغيير
                unset($validated['status'], $validated['balance']);
            }
            // إذا كان المستخدم يُحدّث نفسه، يمكنه تغيير هذه الحقول (مثل كلمة المرور)
            if ($authUser->id === $user->id) {
                // قد ترغب في السماح بتغيير حقول معينة هنا حتى لو لم يكن لديه صلاحيات عامة
                // مثلاً، يمكنه تحديث بروفايله الخاص ولكن ليس حالته أو رصيده.
                // حالياً المنطق يمنعها إذا لم يكن له صلاحيات عامة
            }

            // تحديث كلمة المرور فقط إذا تم إرسالها
            if (!empty($validated['password'])) {
                $user->password = $validated['password'];
            }

            $user->update($validated);

            if ($authUser->hasAnyPermission(['super_admin', 'company_owner'])) {  // فقط من لديه الصلاحيات الكافية يمكنه تعديل الصلاحيات والشركات
                if (isset($validated['permissions'])) {  // استخدام isset بدلاً من !empty للسماح بتعيين مصفوفة فارغة
                    $user->syncPermissions($validated['permissions']);
                }
                if (isset($validated['company_ids'])) {
                    $user->companies()->detach();  // إزالة كل العلاقات القديمة

                    $user->companies()->syncWithPivotValues(
                        $validated['company_ids'],
                        [
                            'created_by' => $authUser->id,
                            'updated_at' => now(),
                        ]
                    );

                    logger()->info('بعد المزامنة:', $user->companies()->pluck('companies.id')->toArray());
                }
            }

            $user->logUpdated(' المستخدم ' . $user->nickname);
            DB::commit();

            return new UserResource($user->load($this->relations));  // حمل العلاقات بعد التحديث
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
                'message' => 'حدث خطأ أثناء تحديث المستخدم.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => app()->isLocal() ? $e->getTrace() : null,  // لو انت بتطور محليًا فقط
                'user_id' => $authUser?->id,
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request)
    {
        $authUser = auth()->user();

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

            // تطبيق منطق الصلاحيات على المستخدمين الذين سيتم حذفهم
            if ($authUser->hasAnyPermission(['users_delete', 'users_all', 'super_admin'])) {
                // لا حاجة لـ scope خاص
            } elseif ($authUser->hasPermissionTo('company_owner')) {
                $usersToDelete->scopeCompany();
            } elseif ($authUser->hasPermissionTo('users_delete_own')) {
                $usersToDelete->scopeOwn();
            } elseif ($authUser->hasPermissionTo('users_delete_self')) {
                $usersToDelete->scopeSelf();  // هذا سيسمح فقط بحذف نفسه
            } else {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to delete any of the specified users.'
                ], 403);
            }

            // منع المستخدم من حذف نفسه إذا كان لديه فقط صلاحية users_delete_self
            if ($authUser->hasPermissionTo('users_delete_self') && !$authUser->hasAnyPermission(['users_delete', 'users_all', 'super_admin', 'company_owner'])) {
                if (count($userIds) > 1 || (count($userIds) == 1 && $userIds[0] != $authUser->id)) {
                    return response()->json(['error' => 'You can only delete your own account with this permission.'], 403);
                }
            }

            // جلب المستخدمين المصرح لهم بالحذف
            $authorizedUsers = $usersToDelete->get();

            if ($authorizedUsers->isEmpty()) {
                // هذا يعني أن لا يوجد مستخدم من الـ IDs المرسلة مصرح للمستخدم الحالي بحذفه
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'No users found or you do not have permission to delete any of the specified users.'
                ], 403);
            }

            // تأكد أن جميع الـ IDs المرسلة موجودة ومرخص لها بالحذف
            // لمنع حذف مستخدم غير مصرح به في قائمة جزئية
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
                // حذف الخزائن المتعلقة بالمستخدم
                $user->cashBoxes()->delete();
                // يمكن إضافة عمليات حذف أخرى متعلقة بالمستخدم هنا (مثل المعاملات، الفواتير، إلخ)
                $user->delete();
                $user->logForceDeleted(' المستخدم ' . $user->nickname);
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
                // 'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function usersSearch(Request $request)
    {
        try {
            $authUser = auth()->user();

            if (!$authUser) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required.'
                ], 401);
            }

            $query = User::query();

            // تطبيق منطق الصلاحيات على دالة البحث
            if ($authUser->hasAnyPermission(['users_all', 'super_admin'])) {
                // لا حاجة لـ scope خاص
            } elseif ($authUser->hasPermissionTo('company_owner')) {
                $query->scopeCompany();
            } elseif ($authUser->hasPermissionTo('users_show_own')) {  // افترض أن البحث يندرج تحت صلاحية الرؤية
                $query->scopeOwn();
            } elseif ($authUser->hasPermissionTo('users_show_self')) {
                $query->scopeSelf();  // هذا سيسمح بالبحث عن المستخدم نفسه فقط
            } else {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to search for users.'
                ], 403);
            }

            // استبعاد المستخدم الحالي من نتائج البحث إذا لم يكن super_admin
            if (!$authUser->hasAnyPermission(['super_admin', 'users_all', 'users_show'])) {  // استثني صلاحية 'users_show' هنا لو كانت تسمح برؤية أي مستخدم
                $query->where('id', '<>', $authUser->id);
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                // إذا كان البحث أقل من 4 أحرف، ابحث بالـ ID فقط
                if (strlen($search) < 4) {
                    $query->where('id', $search);
                } else {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('id', $search)
                            ->orWhere('nickname', 'like', '%' . $search . '%')  // أضفت البحث بالـ nickname
                            ->orWhere('email', 'like', '%' . $search . '%')  // أضفت البحث بالـ email
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    });
                }
            }

            $perPage = max(1, $request->input('per_page', 10));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query->orderBy($sortField, $sortOrder);

            $users = $query->with('companies')->paginate($perPage);

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
                'user_id' => auth()->id(),
            ]);
            return response()->json([
                'error' => true,
                'message' => 'حدث خطأ أثناء البحث عن المستخدمين.',
            ], 500);
        }
    }

    public function setDefaultCashBox(User $user, $cashBoxId)
    {
        $authUser = auth()->user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        // التحقق من الصلاحيات لتغيير الخزنة الافتراضية
        // يمكن للمشرف العام أو مالك الشركة أو المستخدم نفسه تغيير خزنته الافتراضية
        $canUpdate = $authUser->hasAnyPermission(['super_admin', 'company_owner']) || ($authUser->id === $user->id);

        if (!$canUpdate) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to change the default cash box for this user.'
            ], 403);
        }

        // التأكد من أن المستخدم ينتمي لشركة المستخدم الحالي إذا كان شركة_مالك
        if ($authUser->hasPermissionTo('company_owner') && $authUser->company_id !== $user->company_id) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You can only manage users within your company.'
            ], 403);
        }

        try {
            $cashBox = $user->cashBoxes()->where('id', $cashBoxId)->firstOrFail();
            DB::beginTransaction();
            $user->cashBoxes()->update(['is_default' => 0]);
            $cashBox->update(['is_default' => 1]);
            DB::commit();
            return response()->json(['message' => 'Default cash box updated successfully']);
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
                'message' => 'حدث خطأ أثناء تحديث الخزنة الافتراضية.',
                // 'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function changeCompany(Request $request, User $user)
    {
        $authUser = auth()->user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        // التحقق من الصلاحيات لتغيير شركة المستخدم
        // فقط super_admin يمكنه تغيير شركة أي مستخدم
        // company_owner يمكنه تغيير شركة مستخدمين شركته فقط (افتراضاً هذا ليس منطقي لأنهم ينتمون لشركته بالفعل)
        // ولكن ربما هو لتغيير الشركة الرئيسية لمستخدم داخل شركته؟
        // سأفترض هنا أن super_admin فقط من لديه هذه الصلاحية.
        if (!$authUser->hasPermissionTo('super_admin')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to change the company of this user.'
            ], 403);
        }

        $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);

        try {
            DB::beginTransaction();
            $user->update([
                'company_id' => $request->company_id,
            ]);
            $user->logUpdated(' تم تغيير الشركة الرئيسية للمستخدم ' . $user->nickname);
            DB::commit();
            return response()->json([
                'message' => 'Company updated successfully.',
                'user' => new UserResource($user->load($this->relations)),
            ], 200);
        } catch (Throwable $e) {
            DB::rollback();
            Log::error('Change user company failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $authUser ? $authUser->id : null,
            ]);
            return response()->json([
                'message' => 'حدث خطأ أثناء تغيير شركة المستخدم.',
                // 'details' => $e->getMessage(),
            ], 500);
        }
    }
}

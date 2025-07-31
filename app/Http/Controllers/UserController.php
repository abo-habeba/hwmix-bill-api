<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\User;
use App\Models\CashBox;
use App\Models\CompanyUser;
use App\Models\CashBoxType;
use Illuminate\Http\Request;
use App\Services\CashBoxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\User\UserRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\User\UserUpdateRequest;
use App\Http\Resources\CompanyUser\CompanyUserResource;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Http\Resources\CompanyUser\CompanyUserBasicResource;


class UserController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'companies.logo',
            'cashBoxes',
            'cashBoxDefault',
            'creator',
            'companyUsers',
            'activeCompanyUser.company',
        ];
    }

    /**
     * عرض قائمة المستخدمين بناءً على الصلاحيات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();
        try {
            if (!$authUser) {
                return api_unauthorized('يجب تسجيل الدخول.');
            }

            $activeCompanyId = $authUser->company_id;
            $canViewAll = $authUser->hasPermissionTo(perm_key('users.view_all'));
            $canViewChildren = $authUser->hasPermissionTo(perm_key('users.view_children'));
            $canViewSelf = $authUser->hasPermissionTo(perm_key('users.view_self'));
            $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
            $isCompanyAdmin = $authUser->hasPermissionTo(perm_key('admin.company')); // تم تصحيح هذا السطر

            // إذا كان المستخدم ليس Super Admin ولا مدير شركة، أو لا توجد شركة نشطة له، يمنع العرض
            if (
                !$isSuperAdmin &&
                (!$isCompanyAdmin || !$activeCompanyId) &&
                !$canViewAll &&
                !$canViewChildren &&
                !$canViewSelf
            ) {
                return api_forbidden('ليس لديك صلاحية لعرض المستخدمين أو لا توجد شركة نشطة مرتبطة بك.');
            }

            $query = CompanyUser::with([
                'user' => fn($q) => $q->with(['cashBoxes', 'creator', 'companies.logo']), // أضف علاقة companies.logo هنا أيضًا
                'company',
            ]);

            // تطبيق منطق الصلاحيات بناءً على company_id للمستخدم الموثق
            if ($isSuperAdmin) {
                // المدير العام يرى كل المستخدمين في كل الشركات (من خلال company_users)
                // لا يوجد شرط company_id هنا
            } elseif ($activeCompanyId) { // المستخدم لديه شركة نشطة
                if ($isCompanyAdmin || $canViewAll) { // تم تصحيح $isSuperAdmin إلى $isCompanyAdmin
                    // مدير الشركة أو من لديه صلاحية view_all يرى المستخدمين المرتبطين بشركته النشطة فقط
                    $query->where('company_id', $activeCompanyId);
                } elseif ($canViewChildren) {
                    // المستخدم يرى المستخدمين الذين أنشأهم هو أو من تحت إدارته في سياق شركته
                    $descendantUserIds = $authUser->getDescendantUserIds(); // تحتاج لـ getDescendantUserIds معدلة
                    $query->where('company_id', $activeCompanyId)
                        ->whereIn('user_id', $descendantUserIds); // التأكد من الشركة النشطة
                } elseif ($canViewSelf) {
                    // المستخدم يرى نفسه فقط في سياق شركته
                    $query->where('company_id', $activeCompanyId)
                        ->where('user_id', $authUser->id);
                } else {
                    return api_forbidden('ليس لديك صلاحية لعرض المستخدمين في هذه الشركة.');
                }
            } else { // لا توجد شركة نشطة للمستخدم و ليس Super Admin
                // إذا وصل هنا، فهذا يعني أنه ليس سوبر أدمن، وليس لديه شركة نشطة، وليس لديه صلاحية view_all/children
                // إذا كان لديه view_self، فسيتم التعامل معها في بداية الدالة (إذا لم يكن هناك activeCompanyId)
                return api_forbidden('ليس لديك صلاحية لعرض المستخدمين أو لا توجد شركة نشطة مرتبطة بك.');
            }

            // تطبيق فلاتر البحث على حقول company_users أو user
            if ($request->filled('nickname')) {
                $query->where('nickname_in_company', 'like', '%' . $request->input('nickname') . '%');
            }
            if ($request->filled('email')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('email', 'like', '%' . $request->input('email') . '%');
                });
            }
            if ($request->filled('phone')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('phone', 'like', '%' . $request->input('phone') . '%');
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status')); // تم تصحيح اسم الحقل
            }
            if ($request->filled('created_at_from')) {
                // يجب أن تكون هذه على `company_users.created_at`
                $query->where('company_users.created_at', '>=', $request->input('created_at_from') . ' 00:00:00');
            }
            if ($request->filled('created_at_to')) {
                // يجب أن تكون هذه على `company_users.created_at`
                $query->where('company_users.created_at', '<=', $request->input('created_at_to') . ' 23:59:59');
            }

            // الفرز والتصفح
            $perPage = max(1, $request->input('per_page', 10));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            // الفرز يجب أن يأخذ في الاعتبار حقول CompanyUser أو User
            if (in_array($sortField, ['nickname_in_company', 'status', 'balance_in_company'])) { // تم تصحيح اسم الحقل
                $query->orderBy($sortField, $sortOrder);
            } elseif (in_array($sortField, ['user_phone', 'user_email', 'user_username'])) {
                $query->join('users', 'company_users.user_id', '=', 'users.id')
                    ->orderBy('users.' . str_replace('user_', '', $sortField), $sortOrder)
                    ->select('company_users.*'); // اختر كل أعمدة company_users لتجنب تضارب الأسماء
            } else {
                $query->orderBy('company_users.id', $sortOrder); // الترتيب الافتراضي لـ company_users id
            }

            $companyUsers = $query->paginate($perPage);

            if ($companyUsers->isEmpty()) {
                return api_success([], 'لم يتم العثور على مستخدمين في هذه الشركة.');
            } else {
                // نستخدم CompanyUserResource هنا لأننا نرجع CompanyUser Models
                return api_success(CompanyUserBasicResource::collection($companyUsers), 'تم جلب المستخدمين بنجاح.');
            }
        } catch (Throwable $e) {
            Log::error("فشل جلب قائمة المستخدمين: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }

    /**
     * إنشاء مستخدم جديد.
     *
     * @param UserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function store(UserRequest $request)
    {
        $authUser = Auth::user();

        // صلاحيات إنشاء مستخدم / عميل
        if (!$authUser || (
            !$authUser->hasPermissionTo(perm_key('admin.super')) &&
            !$authUser->hasPermissionTo(perm_key('users.create')) &&
            !$authUser->hasPermissionTo(perm_key('admin.company'))
        )) {
            return api_forbidden('ليس لديك صلاحية لإنشاء مستخدمين.');
        }

        DB::beginTransaction();
        try {
            $validatedData = $request->validated();
            $activeCompanyId = $authUser->company_id;

            // إذا لم يكن سوبر أدمن، يجب أن تكون هناك شركة نشطة لإنشاء المستخدمين
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$activeCompanyId) {
                DB::rollback();
                return api_forbidden('لإنشاء مستخدمين، يجب أن تكون مرتبطًا بشركة نشطة.');
            }

            // البحث عن المستخدم بـ phone أو email بشكل دقيق
            $user = null;
            if (!empty($validatedData['phone'])) {
                $user = User::where('phone', $validatedData['phone'])->first();
            }

            if (!$user && !empty($validatedData['email'])) {
                $user = User::where('email', $validatedData['email'])->first();
            }

            // إذا لم يكن المستخدم موجودًا، قم بإنشاء سجل جديد في جدول 'users'
            if (!$user) {
                $userDataForUserTable = [
                    'username' => $validatedData['username'],
                    'email'    => $validatedData['email'],
                    'phone'    => $validatedData['phone'],
                    'full_name'    => $validatedData['full_name'],
                    'nickname'    => $validatedData['nickname'],
                    'password' => $validatedData['password'], // يتم هاش الباسورد في الـ User Model
                    'created_by' => $authUser->id,
                    'company_id' => $activeCompanyId, // يتم تعيين الشركة النشطة هنا
                ];
                $user = User::create($userDataForUserTable);
            } else {
                // إذا كان المستخدم موجوداً، يجب التحقق من أنه لا يرتبط بالشركة النشطة
                $companyUserExists = CompanyUser::where('user_id', $user->id)
                    ->where('company_id', $activeCompanyId)
                    ->exists();
                if ($companyUserExists) {
                    DB::rollback();
                    return api_error('هذا المستخدم موجود بالفعل في الشركة النشطة.', [], 409);
                }
                // تحديث company_id للمستخدم الحالي إذا كان موجوداً وتم إنشاءه في سياق شركة أخرى
                if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    $user->update(['company_id' => $activeCompanyId]);
                }
            }

            // الآن، قم بإنشاء سجل في 'company_users' لربط المستخدم بالشركة النشطة
            $companyUserData = [
                'user_id'                  => $user->id,
                'company_id'               => $activeCompanyId,
                'nickname_in_company'      => $validatedData['nickname'] ?? $user->username,
                'full_name_in_company'      => $validatedData['full_name'] ?? $user->username,
                'balance_in_company'       => $validatedData['balance'] ?? 0,
                'customer_type_in_company' => $validatedData['customer_type'] ?? 'default',
                'status'                   => $validatedData['status'] ?? 'active',
                'position_in_company'      => $validatedData['position'] ?? null,
                'created_by'               => $authUser->id,
                'user_phone'               => $user->phone,
                'user_email'               => $user->email,
                'user_username'            => $user->username,
            ];

            $companyUser = CompanyUser::create($companyUserData);

            // يمكنك هنا معالجة ربط المستخدم بشركات إضافية إذا كانت الصلاحيات تسمح بذلك (للسوبر أدمن مثلاً)
            if ($authUser->hasPermissionTo(perm_key('admin.super')) && $request->has('company_ids')) {
                $companyIdsFromRequest = collect($request->input('company_ids'))
                    ->filter(fn($id) => filter_var($id, FILTER_VALIDATE_INT) !== false && (int)$id > 0)
                    ->mapWithKeys(function ($id) use ($authUser, $user, $validatedData) {
                        return [
                            $id => [
                                'created_by'               => $authUser->id,
                                'nickname_in_company'      => $validatedData['nickname'] ?? $user->username,
                                'full_name_in_company'      => $validatedData['full_name'] ?? $user->username,
                                'balance_in_company'       => $validatedData['balance'] ?? 0,
                                'customer_type_in_company' => $validatedData['customer_type'] ?? 'default',
                                'status'                   => $validatedData['status'] ?? 'active',
                                'position_in_company'      => $validatedData['position'] ?? null,
                                'user_phone'               => $user->phone,
                                'user_email'               => $user->email,
                                'user_username'            => $user->username,
                            ]
                        ];
                    })->toArray();
                foreach ($companyIdsFromRequest as $companyId => $pivotData) {
                    // إذا كانت الشركة النشطة موجودة في company_ids، لا ننشئ لها CompanyUser آخر
                    if ($companyId == $activeCompanyId) {
                        continue;
                    }
                    CompanyUser::updateOrCreate(
                        ['user_id' => $user->id, 'company_id' => $companyId],
                        $pivotData
                    );
                }
            }

            // إنشاء صناديق المستخدم الافتراضية لكل شركة (إذا لم تكن موجودة)
            $user->ensureCashBoxesForAllCompanies();

            // images_ids
            if ($request->has('images_ids')) {
                $imagesIds = $request->input('images_ids');
                $user->syncImages($imagesIds, 'avatar');
            }

            $user->logCreated('بانشاء المستخدم ' . ($companyUser->nickname_in_company ?? $user->username) . ' في الشركة ' . $companyUser->company->name);
            DB::commit();

            return api_success(new CompanyUserResource($companyUser->load(['user.cashBoxes', 'user.cashBoxDefault', 'user.creator', 'company'])), 'تم إنشاء المستخدم بنجاح.');
        } catch (Throwable $e) {
            DB::rollback();
            Log::error("فشل إنشاء المستخدم: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }
    /**
     * عرض بيانات مستخدم واحد.
     *
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(User $user)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        $activeCompanyId = $authUser->company_id;
        $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
        $canViewAll = $authUser->hasPermissionTo(perm_key('users.view_all'));
        $canViewChildren = $authUser->hasPermissionTo(perm_key('users.view_children'));
        $canViewSelf = $authUser->hasPermissionTo(perm_key('users.view_self'));

        // للـ Super Admin، يمكنه عرض أي مستخدم
        if ($isSuperAdmin) {
            // نعود بـ UserResource الذي يحمل بيانات المستخدم الأساسية وجميع علاقات companyUsers
            $user->load($this->relations);
            return api_success(new UserResource($user), 'تم جلب بيانات المستخدم بنجاح.');
        }

        // للمستخدم نفسه، يعرض بياناته الشخصية وبياناته في الشركة النشطة
        if ($authUser->id === $user->id && $canViewSelf) {
            // هنا نفضل أن نعود بـ CompanyUserResource للمستخدم نفسه في سياق شركته النشطة
            // لأن هذا هو السياق الذي يعمل فيه
            $companyUser = $user->activeCompanyUser()->first(); // جلب CompanyUser للشركة النشطة
            if ($companyUser) {
                $companyUser->load(['user.cashBoxes', 'user.cashBoxDefault', 'user.creator', 'company']); // تم تصحيح cashBoxDefault
                return api_success(new CompanyUserResource($companyUser), 'تم جلب بيانات المستخدم بنجاح.');
            }
            // إذا لم يكن لديه سجل في CompanyUser للشركة النشطة (حالة نادرة بعد التعديلات)
            $user->load($this->relations); // نعود ببيانات المستخدم الأساسية
            return api_success(new UserResource($user), 'تم جلب بيانات المستخدم بنجاح.');
        }

        // لمدير الشركة أو من لديه صلاحية view_all (في سياق الشركة النشطة)
        if (($authUser->hasPermissionTo(perm_key('admin.company')) || $canViewAll) && $activeCompanyId) {
            $companyUser = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $activeCompanyId)
                ->with(['user.cashBoxes', 'user.cashBoxDefault', 'user.creator', 'company']) // تم تصحيح cashBoxDefault
                ->first();

            if (!$companyUser) {
                return api_not_found('المستخدم غير موجود أو ليس لديه علاقة بالشركة النشطة.');
            }
            // هنا نعود بـ CompanyUserResource لأننا جلبنا CompanyUser
            return api_success(new CompanyUserResource($companyUser), 'تم جلب بيانات المستخدم بنجاح في سياق الشركة.');
        }

        // للمستخدمين الذين يرون المستخدمين الذين أنشأوهم أو من تحتهم
        if ($canViewChildren && $activeCompanyId) {
            $descendantUserIds = $authUser->getDescendantUserIds();

            // يجب أن يكون المستخدم الهدف موجودًا في قائمة التابعين
            // وهذا المستخدم يجب أن يكون مرتبطًا بالشركة النشطة
            $companyUser = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $activeCompanyId)
                ->whereIn('user_id', $descendantUserIds) // التأكد أن المستخدم ضمن التابعين
                ->with(['user.cashBoxes', 'user.cashBoxDefault', 'user.creator', 'company']) // تم تصحيح cashBoxDefault
                ->first();

            if (!$companyUser) {
                return api_forbidden('ليس لديك صلاحية لعرض هذا المستخدم أو المستخدم غير مرتبط بالشركة النشطة.'); // رسالة أكثر دقة
            }
            return api_success(new CompanyUserResource($companyUser), 'تم جلب بيانات المستخدم بنجاح في سياق الشركة.');
        }

        return api_forbidden('ليس لديك صلاحية لعرض هذا المستخدم.');
    }

    /**
     * تحديث بيانات مستخدم.
     *
     * @param UserUpdateRequest $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        $validated = $request->validated();
        $activeCompanyId = $authUser->company_id;

        $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
        $isCompanyAdmin = $authUser->hasPermissionTo(perm_key('admin.company'));
        $canUpdateAllUsers = $authUser->hasPermissionTo(perm_key('users.update_all'));
        $canUpdateChildren = $authUser->hasPermissionTo(perm_key('users.update_children'));
        $canUpdateSelf = $authUser->hasPermissionTo(perm_key('users.update_self'));

        Log::info('Update User Request Initiated', [
            'auth_user_id' => $authUser->id,
            'target_user_id' => $user->id,
            'is_super_admin' => $isSuperAdmin,
            'is_company_admin' => $isCompanyAdmin,
            'can_update_all_users' => $canUpdateAllUsers,
            'can_update_children' => $canUpdateChildren,
            'can_update_self' => $canUpdateSelf,
            'validated_data' => $validated, // عرض جميع البيانات المتحقق منها
        ]);

        DB::beginTransaction();
        try {
            // متغير لتتبع ما إذا كان المستخدم الحالي هو نفسه المستخدم المستهدف
            $isUpdatingSelf = ($authUser->id === $user->id);

            // --- تحديث جدول المستخدمين (users) ---
            $userDataToUpdate = [];
            if (isset($validated['username'])) $userDataToUpdate['username'] = $validated['username'];
            if (isset($validated['email']))    $userDataToUpdate['email']    = $validated['email'];
            if (isset($validated['phone']))    $userDataToUpdate['phone']    = $validated['phone'];
            if (isset($validated['password'])) $userDataToUpdate['password'] = $validated['password'];
            if (isset($validated['nickname'])) $userDataToUpdate['nickname'] = $validated['nickname'];
            if (isset($validated['full_name'])) $userDataToUpdate['full_name'] = $validated['full_name'];
            if (isset($validated['position'])) $userDataToUpdate['position'] = $validated['position'];
            if (isset($validated['settings'])) $userDataToUpdate['settings'] = $validated['settings'];
            if (isset($validated['last_login_at'])) $userDataToUpdate['last_login_at'] = $validated['last_login_at'];
            if (isset($validated['email_verified_at'])) $userDataToUpdate['email_verified_at'] = $validated['email_verified_at'];
            // created_by لا يتم تحديثه عادةً هنا

            Log::info('User Data To Update (users table)', ['data' => $userDataToUpdate]);

            // منطق تحديث جدول users: المستخدم نفسه بصلاحية update_self أو السوبر أدمن
            if ($isUpdatingSelf) {
                Log::info('Updating self scenario');
                if (!$canUpdateSelf) {
                    DB::rollback();
                    Log::warning('Forbidden: User attempting to update self without users.update_self permission.');
                    return api_forbidden('ليس لديك صلاحية لتعديل حسابك الشخصي.');
                }
                if (!empty($userDataToUpdate)) {
                    $user->update($userDataToUpdate);
                    Log::info('User (self) table updated successfully.', ['user_id' => $user->id, 'updated_fields' => array_keys($userDataToUpdate)]);
                } else {
                    Log::info('User (self) table not updated: No user data to update provided.');
                }
            } elseif ($isSuperAdmin) {
                Log::info('Super Admin updating other user scenario');
                if (!empty($userDataToUpdate)) {
                    $user->update($userDataToUpdate);
                    Log::info('User (super admin) table updated successfully.', ['user_id' => $user->id, 'updated_fields' => array_keys($userDataToUpdate)]);
                } else {
                    Log::info('User (super admin) table not updated: No user data to update provided.');
                }
            } else {
                Log::info('User (non-super admin, non-self) attempting to update users table. No update allowed for users table.');
                // لا يوجد منع صريح هنا كما كان من قبل، فقط يتم تخطي التحديث
            }


            // --- تحديث جدول الشركة-المستخدم الوسيط (company_users) ---
            $companyUserDataToUpdate = [];
            // الحقول التي يمكن تحديثها في جدول company_users من الـ $validated
            if (isset($validated['nickname'])) $companyUserDataToUpdate['nickname_in_company'] = $validated['nickname'];
            if (isset($validated['full_name'])) $companyUserDataToUpdate['full_name_in_company'] = $validated['full_name'];
            if (isset($validated['position'])) $companyUserDataToUpdate['position_in_company'] = $validated['position'];
            if (isset($validated['customer_type'])) $companyUserDataToUpdate['customer_type_in_company'] = $validated['customer_type'];
            if (isset($validated['status'])) $companyUserDataToUpdate['status'] = $validated['status'];
            if (isset($validated['balance'])) $companyUserDataToUpdate['balance_in_company'] = $validated['balance'];

            // هذه الحقول يجب أن تأتي من User Model بعد تحديثه، لضمان التناسق
            // (مهم: يجب أن يكون موديل User قد تم تحديثه بالفعل في الخطوة السابقة إذا سمحت الصلاحيات)
            $companyUserDataToUpdate['user_phone']    = $user->phone;
            $companyUserDataToUpdate['user_email']    = $user->email;
            $companyUserDataToUpdate['user_username'] = $user->username;

            Log::info('CompanyUser Data To Update (company_users table)', ['data' => $companyUserDataToUpdate]);


            // تحديد ما إذا كان مسموحًا بتحديث بيانات company_users
            $canUpdateCompanyUser = false;
            if ($isSuperAdmin || $isCompanyAdmin || $canUpdateAllUsers) {
                $canUpdateCompanyUser = true;
            } elseif ($canUpdateChildren) {
                $descendantUserIds = $authUser->getDescendantUserIds();
                $canUpdateCompanyUser = in_array($user->id, $descendantUserIds);
            } elseif ($isUpdatingSelf && $canUpdateSelf) {
                $canUpdateCompanyUser = true;
            }

            Log::info('Can Update Company User?', ['can_update_company_user' => $canUpdateCompanyUser, 'active_company_id' => $activeCompanyId]);

            if ($canUpdateCompanyUser) {
                $companyUser = CompanyUser::where('user_id', $user->id)
                    ->where('company_id', $activeCompanyId)
                    ->first();

                if (!$companyUser) {
                    DB::rollback();
                    Log::warning('Forbidden: CompanyUser not found for target user in active company.', ['user_id' => $user->id, 'company_id' => $activeCompanyId]);
                    return api_not_found('المستخدم غير مرتبط بالشركة النشطة لتعديل بياناته.');
                }
                if (!empty($companyUserDataToUpdate)) {
                    $companyUser->update($companyUserDataToUpdate);
                    Log::info('CompanyUser table updated successfully.', ['company_user_id' => $companyUser->id, 'updated_fields' => array_keys($companyUserDataToUpdate)]);
                } else {
                    Log::info('CompanyUser table not updated: No company user data to update provided.');
                }
            }

            // معالجة images_ids (تظل كما هي)
            if ($request->has('images_ids')) {
                $imagesIds = $request->input('images_ids');
                $user->syncImages($imagesIds, 'avatar');
                Log::info('Images synced for user.', ['user_id' => $user->id, 'image_ids' => $imagesIds]);
            }

            // معالجة company_ids لمزامنة علاقات المستخدم بالشركات الأخرى (فقط للسوبر أدمن) (تظل كما هي)
            if ($isSuperAdmin && array_key_exists('company_ids', $validated)) {
                Log::info('Super Admin syncing company_ids for user.', ['user_id' => $user->id, 'company_ids_from_request' => $validated['company_ids']]);
                $companyIdsFromRequest = collect($validated['company_ids'])
                    ->filter(fn($id) => !empty($id) && is_numeric($id))
                    ->values()
                    ->toArray();

                // للحفاظ على مزامنة company_id في جدول users مع إحدى الشركات النشطة
                if (!empty($companyIdsFromRequest)) {
                    if ($user->company_id && !in_array($user->company_id, $companyIdsFromRequest)) {
                        $user->update(['company_id' => $companyIdsFromRequest[0]]);
                        Log::info('User main company_id updated.', ['user_id' => $user->id, 'new_company_id' => $companyIdsFromRequest[0]]);
                    } elseif (!$user->company_id) {
                        $user->update(['company_id' => $companyIdsFromRequest[0]]);
                        Log::info('User main company_id set for the first time.', ['user_id' => $user->id, 'new_company_id' => $companyIdsFromRequest[0]]);
                    }
                } else {
                    $user->update(['company_id' => null]);
                    Log::info('User main company_id set to null as no companies provided.', ['user_id' => $user->id]);
                }

                foreach ($companyIdsFromRequest as $companyId) {
                    CompanyUser::updateOrCreate(
                        ['user_id' => $user->id, 'company_id' => $companyId],
                        [
                            'created_by'               => $authUser->id,
                            'nickname_in_company'      => $validated['nickname'] ?? null,
                            'full_name_in_company'     => $validated['full_name'] ?? null,
                            'balance_in_company'       => $validated['balance'] ?? 0,
                            'customer_type_in_company' => $validated['customer_type'] ?? 'default',
                            'status'                   => $validated['status'] ?? 'active',
                            'position_in_company'      => $validated['position'] ?? null,
                            'user_phone'               => $validated['phone'] ?? $user->phone,
                            'user_email'               => $validated['email'] ?? $user->email,
                            'user_username'            => $validated['username'] ?? $user->username,
                        ]
                    );
                    Log::info('CompanyUser relation updated/created.', ['user_id' => $user->id, 'company_id' => $companyId]);
                }
                // حذف العلاقات التي لم تعد موجودة في الطلب
                $user->companyUsers()->whereNotIn('company_id', $companyIdsFromRequest)->delete();
                Log::info('CompanyUser relations deleted for removed companies.', ['user_id' => $user->id, 'removed_companies' => $user->companyUsers()->whereNotIn('company_id', $companyIdsFromRequest)->pluck('company_id')->toArray()]);
                app(CashBoxService::class)->ensureCashBoxesForUserCompanies($user, $companyIdsFromRequest); // تحديث صناديق الكاش بناءً على الشركات المحدثة
                Log::info('Cash boxes ensured for user companies.', ['user_id' => $user->id]);
            }

            $user->logUpdated('بتحديث المستخدم ' . ($user->activeCompanyUser->nickname_in_company ?? $user->username));
            DB::commit();
            Log::info('User update transaction committed successfully.', ['user_id' => $user->id]);

            // العودة بـ UserResource محملًا بالبيانات المحدثة (خاصة activeCompanyUser)
            return api_success(new UserResource($user->load($this->relations)), 'تم تحديث المستخدم بنجاح');
        } catch (Throwable $e) {
            DB::rollback();
            Log::error("فشل تحديث المستخدم: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'target_user_id' => $user->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }
    /**
     * حذف المستخدمين.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        $userIds = $request->input('item_ids');
        if (!$userIds || !is_array($userIds) || empty($userIds)) {
            return api_error('لم يتم تحديد معرفات المستخدمين بشكل صحيح', [], 400);
        }

        DB::beginTransaction();
        try {
            $usersToDelete = User::whereIn('id', $userIds)->get(); // جلب المستخدمين أولاً
            $activeCompanyId = $authUser->company_id;
            $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
            $isCompanyAdmin = $authUser->hasPermissionTo(perm_key('admin.company')); // تم تصحيح هذا السطر
            $canDeleteAll = $authUser->hasPermissionTo(perm_key('users.delete_all'));
            $canDeleteChildren = $authUser->hasPermissionTo(perm_key('users.delete_children'));
            $canDeleteSelf = $authUser->hasPermissionTo(perm_key('users.delete_self'));

            $deletedCount = 0;
            $descendantUserIds = [];
            if ($canDeleteChildren) {
                $descendantUserIds = $authUser->getDescendantUserIds();
            }

            foreach ($usersToDelete as $user) {
                // لا يمكن حذف الحساب النشط
                if ($user->id === $authUser->id) {
                    continue; // تخطي المستخدم الحالي
                }

                if ($isSuperAdmin || $canDeleteAll) {
                    // حذف كامل للمستخدم وعلاقاته وصناديقه
                    $user->cashBoxes()->delete();
                    $user->companyUsers()->delete(); // حذف جميع علاقات company_users
                    $user->delete();
                    $user->logForceDeleted('المستخدم ' . $user->username);
                    $deletedCount++;
                } elseif ($activeCompanyId && ($isCompanyAdmin || $canDeleteChildren)) { // تم تصحيح $isSuperAdmin إلى $isCompanyAdmin
                    // مدير الشركة يمكنه فقط حذف علاقة المستخدم بشركته
                    // أو المستخدم يمكنه حذف أطفاله في سياق الشركة النشطة
                    if ($isCompanyAdmin || ($canDeleteChildren && in_array($user->id, $descendantUserIds))) { // تم تصحيح $isSuperAdmin إلى $isCompanyAdmin
                        $companyUser = $user->companyUsers()->where('company_id', $activeCompanyId)->first();
                        if ($companyUser) {
                            $user->cashBoxes()->where('company_id', $activeCompanyId)->delete(); // حذف صناديق الكاش في هذه الشركة
                            $companyUser->delete(); // حذف علاقة المستخدم بهذه الشركة
                            $user->logForceDeleted('علاقة المستخدم ' . ($companyUser->nickname_in_company ?? $user->username) . ' بالشركة ' . $companyUser->company->name);
                            $deletedCount++;

                            // إذا لم يعد المستخدم مرتبطًا بأي شركة أخرى، يمكن حذف سجل المستخدم الأساسي
                            if ($user->companyUsers()->count() === 0 && $user->cashBoxes()->count() === 0) {
                                $user->delete();
                                $user->logForceDeleted('المستخدم ' . $user->username . ' من النظام بعد إزالة جميع ارتباطاته بالشركات.');
                            }
                        }
                    }
                }
                // صلاحية users.delete_self لا يتم التعامل معها هنا لأنها عادةً تكون لنقطة نهاية منفصلة (حذف الحساب الشخصي)
                // وتتطلب تسجيل خروج المستخدم بعد الحذف، ولا يمكن دمجها في عملية حذف جماعي.
            }

            if ($deletedCount === 0) {
                DB::rollBack();
                return api_forbidden('لم يتم حذف أي مستخدمين. تحقق من الصلاحيات أو معرفات المستخدمين.');
            }

            DB::commit();
            return api_success([], 'تم حذف المستخدمين بنجاح');
        } catch (Throwable $e) {
            DB::rollback();
            Log::error("فشل حذف المستخدم: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'item_ids' => $userIds]);
            return api_exception($e);
        }
    }

    /**
     * تغيير الشركة النشطة للمستخدم.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeCompany(Request $request, User $user)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return api_unauthorized('يجب تسجيل الدخول.');
        }

        // الصلاحية المطلوبة لتغيير شركة المستخدم هي 'users.update_all'
        // أو إذا كان المستخدم نفسه يحاول تغيير شركته النشطة (إذا كان هذا مسموحًا)
        if (!$authUser->hasPermissionTo(perm_key('users.update_all')) && $authUser->id !== $user->id) {
            return api_forbidden('ليس لديك صلاحية لتغيير شركة هذا المستخدم.');
        }

        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        DB::beginTransaction();
        try {
            $newCompanyId = $validated['company_id'];

            // تأكد أن المستخدم مرتبط بالشركة الجديدة التي يحاول التبديل إليها
            $companyUserExists = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $newCompanyId)
                ->exists();

            if (!$companyUserExists) {
                DB::rollback();
                return api_error('المستخدم غير مرتبط بالشركة المحددة.', [], 400);
            }

            // تحديث company_id في جدول users
            $user->update(['company_id' => $newCompanyId]);

            // قد تحتاج هنا إلى إعادة تحميل العلاقات لضمان أن activeCompanyUser تعكس التغيير
            $user->load('activeCompanyUser.company');

            $user->logUpdated('بتغيير الشركة النشطة للمستخدم ' . $user->username . ' إلى الشركة رقم ' . $newCompanyId);
            DB::commit();

            return api_success(new UserResource($user), 'تم تغيير الشركة النشطة للمستخدم بنجاح.');
        } catch (Throwable $e) {
            DB::rollback();
            Log::error("فشل تغيير شركة المستخدم: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'target_user_id' => $user->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }

    /**
     * البحث عن المستخدمين.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function usersSearch(Request $request)
    {
        $authUser = Auth::user();
        try {
            if (!$authUser) {
                return api_unauthorized('يجب تسجيل الدخول.');
            }

            $activeCompanyId = $authUser->company_id;
            $isSuperAdmin = $authUser->hasPermissionTo(perm_key('admin.super'));
            $isCompanyAdmin = $authUser->hasPermissionTo(perm_key('admin.company')); // تم تصحيح هذا السطر
            $canViewAll = $authUser->hasPermissionTo(perm_key('users.view_all'));
            $canViewChildren = $authUser->hasPermissionTo(perm_key('users.view_children'));
            $canViewSelf = $authUser->hasPermissionTo(perm_key('users.view_self'));

            // تحديد الاستعلام الأساسي بناءً على الصلاحيات
            $query = User::query(); // نبدأ دائماً من موديل User الرئيسي للبحث الشامل

            // إذا كان المستخدم ليس سوبر أدمن، نطبق الفلاتر اللازمة
            if (!$isSuperAdmin) {
                if ($activeCompanyId) {
                    // المستخدم مرتبط بشركة نشطة
                    $query->whereHas('companies', function ($q) use ($activeCompanyId) {
                        $q->where('companies.id', $activeCompanyId);
                    });

                    // تطبيق صلاحيات العرض على مستوى الشركة
                    if ($canViewAll) {
                        // لا يوجد فلتر إضافي بخلاف الشركة النشطة
                    } elseif ($canViewChildren) {
                        $descendantUserIds = $authUser->getDescendantUserIds();
                        $query->whereIn('id', $descendantUserIds);
                    } elseif ($canViewSelf) {
                        $query->where('id', $authUser->id);
                    } else {
                        // لا صلاحية للعرض في هذه الشركة
                        return api_forbidden('ليس لديك صلاحية للبحث عن المستخدمين في هذه الشركة.');
                    }
                } else {
                    // المستخدم ليس سوبر أدمن وليس لديه شركة نشطة
                    if ($canViewSelf) {
                        $query->where('id', $authUser->id);
                    } else {
                        return api_forbidden('ليس لديك صلاحية للبحث عن المستخدمين.');
                    }
                }
            }

            $query->with($this->relations); // تحميل العلاقات دائمًا

            // تطبيق فلاتر البحث
            if ($request->filled('search')) {
                $search = $request->input('search');
                if ($isSuperAdmin) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('id', $search)
                            ->orWhere('username', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    });
                } elseif ($activeCompanyId) { // للمستخدمين المرتبطين بشركة معينة (مدير شركة، أو view_all/children/self)
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('username', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%')
                            ->orWhereHas('companyUsers', function ($companyUserQuery) use ($search) {
                                $companyUserQuery->where('nickname_in_company', 'like', '%' . $search . '%')
                                    ->orWhere('full_name_in_company', 'like', '%' . $search . '%');
                            });
                    });
                }
            }

            // الفرز والتصفح
            $perPage = max(1, $request->input('per_page', 10));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            if (in_array($sortField, ['username', 'email', 'phone', 'id'])) {
                $query->orderBy($sortField, $sortOrder);
            } else {
                $query->orderBy('id', $sortOrder);
            }

            $users = $query->paginate($perPage);

            if ($users->isEmpty()) {
                return api_success([], 'لم يتم العثور على مستخدمين.');
            } else {
                return api_success(UserResource::collection($users), 'تم جلب المستخدمين بنجاح.');
            }
        } catch (Throwable $e) {
            Log::error("فشل البحث عن المستخدمين: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }
}

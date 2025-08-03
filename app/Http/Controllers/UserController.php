<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\User;
use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\CompanyUser;
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
use Illuminate\Pagination\LengthAwarePaginator;
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
            !$authUser->hasAnyPermission(perm_key('admin.super')) &&
            !$authUser->hasAnyPermission(perm_key('users.create')) &&
            !$authUser->hasAnyPermission(perm_key('admin.company'))
        )) {
            return api_forbidden('ليس لديك صلاحية لإنشاء مستخدمين.');
        }

        DB::beginTransaction();
        try {
            $validatedData = $request->validated();
            $activeCompanyId = $authUser->company_id;

            // إذا لم يكن سوبر أدمن، يجب أن تكون هناك شركة نشطة لإنشاء المستخدمين
            if (!$authUser->hasAnyPermission(perm_key('admin.super')) && !$activeCompanyId) {
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
                    'password' => $validatedData['password'], // يتم هاش الباسورد في الـ User Model
                    'created_by' => $authUser->id,
                    'company_id' => $activeCompanyId, // يتم تعيين الشركة النشطة هنا
                    'full_name' => $validatedData['full_name'] ?? null, // إضافة full_name
                    'nickname' => $validatedData['nickname'] ?? null,   // إضافة nickname
                ];
                $user = User::create($userDataForUserTable);
                Log::info('New User created in users table.', ['user_id' => $user->id]);
            } else {
                // إذا كان المستخدم موجوداً، يجب التحقق من أنه لا يرتبط بالشركة النشطة
                $companyUserExists = CompanyUser::where('user_id', $user->id)
                    ->where('company_id', $activeCompanyId)
                    ->exists();

                Log::info('Existing User found. Checking company_users relation.', [
                    'user_id' => $user->id,
                    'active_company_id' => $activeCompanyId,
                    'company_user_exists' => $companyUserExists
                ]);

                if ($companyUserExists) {
                    DB::rollback();
                    return api_error('هذا المستخدم موجود بالفعل في الشركة النشطة.', [], 409);
                }
                // تحديث company_id للمستخدم الحالي إذا كان موجوداً وتم إنشاءه في سياق شركة أخرى
                // هذا الجزء يسمح لغير السوبر أدمن بتحديث company_id الأساسي للمستخدم
                if (!$authUser->hasAnyPermission(perm_key('admin.super'))) {
                    $user->update(['company_id' => $activeCompanyId]);
                    Log::info('Updated user main company_id for existing user (non-super admin).', ['user_id' => $user->id, 'new_company_id' => $activeCompanyId]);
                }
            }

            // --- إعداد بيانات CompanyUser ---
            $companyUserData = [
                'user_id'                  => $user->id,
                'company_id'               => $activeCompanyId,
                'nickname_in_company'      => $validatedData['nickname'] ?? $user->username, // استخدام $validatedData['nickname']
                'full_name_in_company'     => $validatedData['full_name'] ?? $user->full_name, // استخدام $validatedData['full_name']
                'balance_in_company'       => $validatedData['balance'] ?? 0, // استخدام $validatedData['balance']
                'customer_type_in_company' => $validatedData['customer_type'] ?? 'default',
                'status'                   => $validatedData['status'] ?? 'active',
                'position_in_company'      => $validatedData['position'] ?? null,
                'created_by'               => $authUser->id,
                'user_phone'               => $user->phone,
                'user_email'               => $user->email,
                'user_username'            => $user->username,
            ];

            Log::info('Base CompanyUser data prepared.', ['data' => $companyUserData]);

            $companyUser = null; // تهيئة المتغير الذي سيتم إرجاعه

            // Scenario 1: Super Admin or Company Admin or users.update_all with company_ids array for multi-company sync
            // هذا الجزء سيعالج إنشاء/تحديث سجلات company_users لجميع الشركات في company_ids
            if (($authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company'), perm_key('users.update_all')]) && array_key_exists('company_ids', $validatedData))) { // استخدام $validatedData
                Log::info('Admin/Super Admin/UpdateAll handling company_ids for user creation.', ['user_id' => $user->id, 'company_ids_from_request' => $validatedData['company_ids']]);

                $companyIdsFromRequest = collect($validatedData['company_ids'])
                    ->filter(fn($id) => filter_var($id, FILTER_VALIDATE_INT) !== false && (int)$id > 0)
                    ->values()
                    ->toArray();

                foreach ($companyIdsFromRequest as $companyId) {
                    $currentCompanyUser = CompanyUser::updateOrCreate(
                        ['user_id' => $user->id, 'company_id' => $companyId],
                        array_merge($companyUserData, ['company_id' => $companyId]) // تأكد من استخدام company_id الصحيح لكل دورة
                    );
                    Log::info('CompanyUser relation updated/created (from company_ids loop).', ['user_id' => $user->id, 'company_id' => $companyId, 'company_user_id' => $currentCompanyUser->id]);

                    // إذا كانت هذه هي الشركة النشطة للمستخدم الموثق، فاحتفظ بها كـ $companyUser الرئيسي
                    if ($companyId == $activeCompanyId) {
                        $companyUser = $currentCompanyUser;
                    }
                }

                // إذا لم يتم العثور على $companyUser للشركة النشطة ضمن company_ids التي تم إرسالها
                // (وهذا قد يحدث إذا كانت الشركة النشطة للمستخدم الموثق ليست ضمن company_ids المرسلة)
                if (!$companyUser && $activeCompanyId) {
                    $companyUser = CompanyUser::where('user_id', $user->id)
                        ->where('company_id', $activeCompanyId)
                        ->first();
                    Log::info('Fetched active company user outside of company_ids loop.', ['user_id' => $user->id, 'company_id' => $activeCompanyId]);
                }
            } else {
                // Scenario 2: If company_ids array is NOT provided or permissions are not met for multi-company sync
                // يتم إنشاء سجل company_user واحد فقط للشركة النشطة للمستخدم الموثق
                Log::info('Creating single CompanyUser record for active company.', ['user_id' => $user->id, 'active_company_id' => $activeCompanyId]);
                $companyUser = CompanyUser::create($companyUserData);
                Log::info('Single CompanyUser created for active company.', ['company_user_id' => $companyUser->id]);
            }


            // إنشاء صناديق المستخدم الافتراضية لكل شركة (إذا لم تكن موجودة)
            // هذه الوظيفة في User Model يجب أن تكون مسؤولة عن إنشاء الصناديق بناءً على علاقات companyUsers
            $user->ensureCashBoxesForAllCompanies();
            Log::info('Cash boxes ensured for user companies.', ['user_id' => $user->id]);


            // images_ids
            if ($request->has('images_ids')) {
                $imagesIds = $request->input('images_ids');
                $user->syncImages($imagesIds, 'avatar');
                Log::info('Images synced for user.', ['user_id' => $user->id, 'image_ids' => $imagesIds]);
            }

            $user->logCreated('بانشاء المستخدم ' . ($companyUser->nickname_in_company ?? $user->username) . ' في الشركة ' . $companyUser->company->name);
            DB::commit();
            Log::info('User creation transaction committed successfully.', ['user_id' => $user->id]);

            // العودة بـ CompanyUserResource الذي يمثل المستخدم في سياق الشركة النشطة
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

    public function show(User $user, Request $request)
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

        // تحديد نوع الريسورس المطلوب بناءً على الباراميتر 'basic'
        // القيمة الافتراضية هي true (لإرجاع CompanyUserBasicResource)
        $useBasicResource = filter_var($request->input('basic', true), FILTER_VALIDATE_BOOLEAN);

        // للـ Super Admin، يمكنه عرض أي مستخدم
        if ($isSuperAdmin) {
            // نعود بـ UserResource الذي يحمل بيانات المستخدم الأساسية وجميع علاقات companyUsers
            $user->load($this->relations);
            return api_success(new UserResource($user), 'تم جلب بيانات المستخدم بنجاح.');
        }

        // للمستخدم نفسه، يعرض بياناته الشخصية وبياناته في الشركة النشطة
        if ($authUser->id === $user->id && $canViewSelf) {
            $companyUser = $user->activeCompanyUser()->first();
            if ($companyUser) {
                if ($useBasicResource) {
                    return api_success(new CompanyUserBasicResource($companyUser), 'تم جلب بيانات المستخدم بنجاح.');
                } else {
                    $companyUser->load(['user.cashBoxes', 'user.cashBoxDefault', 'user.creator', 'company']);
                    return api_success(new CompanyUserResource($companyUser), 'تم جلب بيانات المستخدم بنجاح.');
                }
            }
            $user->load($this->relations);
            return api_success(new UserResource($user), 'تم جلب بيانات المستخدم بنجاح.');
        }

        // لمدير الشركة أو من لديه صلاحية view_all (في سياق الشركة النشطة)
        if (($authUser->hasPermissionTo(perm_key('admin.company')) || $canViewAll) && $activeCompanyId) {
            $companyUser = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $activeCompanyId)
                ->first(); // لا نحمل العلاقات هنا مبدئياً

            if (!$companyUser) {
                return api_not_found('المستخدم غير موجود أو ليس لديه علاقة بالشركة النشطة.');
            }

            if ($useBasicResource) {
                return api_success(new CompanyUserBasicResource($companyUser), 'تم جلب بيانات المستخدم بنجاح في سياق الشركة.');
            } else {
                $companyUser->load(['user.cashBoxes', 'user.cashBoxDefault', 'user.creator', 'company']);
                return api_success(new CompanyUserResource($companyUser), 'تم جلب بيانات المستخدم بنجاح في سياق الشركة.');
            }
        }

        // للمستخدمين الذين يرون المستخدمين الذين أنشأوهم أو من تحتهم
        if ($canViewChildren && $activeCompanyId) {
            $descendantUserIds = $authUser->getDescendantUserIds();

            $companyUser = CompanyUser::where('user_id', $user->id)
                ->where('company_id', $activeCompanyId)
                ->whereIn('user_id', $descendantUserIds)
                ->first(); // لا نحمل العلاقات هنا مبدئياً

            if (!$companyUser) {
                return api_forbidden('ليس لديك صلاحية لعرض هذا المستخدم أو المستخدم غير مرتبط بالشركة النشطة.');
            }

            if ($useBasicResource) {
                return api_success(new CompanyUserBasicResource($companyUser), 'تم جلب بيانات المستخدم بنجاح في سياق الشركة.');
            } else {
                $companyUser->load(['user.cashBoxes', 'user.cashBoxDefault', 'user.creator', 'company']);
                return api_success(new CompanyUserResource($companyUser), 'تم جلب بيانات المستخدم بنجاح في سياق الشركة.');
            }
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

        DB::beginTransaction();
        try {
            // متغير لتتبع ما إذا كان المستخدم الحالي هو نفسه المستخدم المستهدف
            $isUpdatingSelf = ($authUser->id === $user->id);

            // --- تحضير بيانات تحديث جدول users ---
            $userDataToUpdate = [];
            if (isset($validated['username'])) $userDataToUpdate['username'] = $validated['username'];
            if (isset($validated['email']))      $userDataToUpdate['email']      = $validated['email'];
            if (isset($validated['phone']))      $userDataToUpdate['phone']      = $validated['phone'];
            if (isset($validated['password']))   $userDataToUpdate['password']   = $validated['password'];
            if (isset($validated['full_name']))  $userDataToUpdate['full_name']  = $validated['full_name'];
            if (isset($validated['position']))   $userDataToUpdate['position']   = $validated['position'];
            if (isset($validated['settings']))   $userDataToUpdate['settings']   = $validated['settings'];
            if (isset($validated['last_login_at'])) $userDataToUpdate['last_login_at'] = $validated['last_login_at'];
            if (isset($validated['email_verified_at'])) $userDataToUpdate['email_verified_at'] = $validated['email_verified_at'];

            // --- حالة تحديث المستخدم لنفسه ---
            if ($isUpdatingSelf && $canUpdateSelf) {
                if (!empty($userDataToUpdate)) {
                    $user->update($userDataToUpdate);
                }
                if ($request->has('images_ids')) {
                    $user->syncImages($request->input('images_ids'), 'avatar');
                }
                $user->logUpdated('بتحديث المستخدم ' . ($user->activeCompanyUser->nickname_in_company ?? $user->username));
                DB::commit();
                Log::info('User self-update transaction committed successfully and function ended.', ['user_id' => $user->id]);
                return api_success(new UserResource($user->load($this->relations)), 'تم تحديث المستخدم بنجاح');
            }

            // --- تحضير بيانات تحديث جدول الشركة-المستخدم الوسيط (company_users) ---
            $companyUserDataToUpdate = [];
            if (isset($validated['nickname'])) $companyUserDataToUpdate['nickname_in_company'] = $validated['nickname'];
            if (isset($validated['full_name'])) $companyUserDataToUpdate['full_name_in_company'] = $validated['full_name'];
            if (isset($validated['position'])) $companyUserDataToUpdate['position_in_company'] = $validated['position'];
            if (isset($validated['customer_type'])) $companyUserDataToUpdate['customer_type_in_company'] = $validated['customer_type'];
            if (isset($validated['status'])) $companyUserDataToUpdate['status'] = $validated['status'];
            if (isset($validated['balance'])) $companyUserDataToUpdate['balance_in_company'] = $validated['balance'];
            if (isset($validated['phone'])) $companyUserDataToUpdate['user_phone'] = $validated['phone'];
            if (isset($validated['email'])) $companyUserDataToUpdate['user_email'] = $validated['email'];
            if (isset($validated['username'])) $companyUserDataToUpdate['user_username'] = $validated['username'];

            Log::info('CompanyUser Data To Update (company_users table) - Base data for syncing:', ['data' => $companyUserDataToUpdate]);

            // تحديد ما إذا كان مسموحًا بتحديث أي سجل company_user
            $canUpdateAnyCompanyUser = false;
            if ($isSuperAdmin || $isCompanyAdmin || $canUpdateAllUsers) {
                $canUpdateAnyCompanyUser = true;
            } elseif ($canUpdateChildren) {
                $descendantUserIds = $authUser->getDescendantUserIds();
                $canUpdateAnyCompanyUser = in_array($user->id, $descendantUserIds);
            }

            // --- منطق تحديث المسؤولين ---
            if ($isSuperAdmin) {
                // تحديث جدول users للمستخدم المستهدف
                if (!empty($userDataToUpdate)) {
                    $user->update($userDataToUpdate);
                }
            } elseif ($canUpdateChildren) {
                // تحقق من صلاحية تحديث الأبناء
                $descendantUserIds = $authUser->getDescendantUserIds();
                if (!in_array($user->id, $descendantUserIds)) {
                    DB::rollback();
                    return api_forbidden('ليس لديك صلاحية لتعديل هذا المستخدم.');
                }
            }

            // --- تحديث العلاقات بين المستخدم والشركات (للمسؤولين) ---
            if ($authUser->hasAnyPermission([perm_key('admin.super'), perm_key('admin.company')]) && array_key_exists('company_ids', $validated) && !empty($validated['company_ids'])) {
                Log::info('Admin/Super Admin syncing multiple company_ids for user.', ['user_id' => $user->id, 'company_ids_from_request' => $validated['company_ids']]);

                $companyIdsFromRequest = collect($validated['company_ids'])
                    ->filter(fn($id) => !empty($id) && is_numeric($id))
                    ->values()
                    ->toArray();

                if (!empty($companyIdsFromRequest)) {
                    foreach ($companyIdsFromRequest as $companyId) {
                        CompanyUser::updateOrCreate(
                            ['user_id' => $user->id, 'company_id' => $companyId],
                            array_merge($companyUserDataToUpdate, [
                                'created_by' => $authUser->id,
                            ])
                        );
                        Log::info('CompanyUser relation updated/created.', ['user_id' => $user->id, 'company_id' => $companyId]);
                    }
                }
            } elseif ($canUpdateAnyCompanyUser && $activeCompanyId) {
                Log::info('Updating active company user record.', ['user_id' => $user->id, 'active_company_id' => $activeCompanyId]);

                $companyUser = CompanyUser::where('user_id', $user->id)->where('company_id', $activeCompanyId)->first();

                if ($companyUser) {
                    $companyUser->update($companyUserDataToUpdate);
                    Log::info('CompanyUser table (active company) updated successfully.', ['company_user_id' => $companyUser->id, 'updated_fields' => array_keys($companyUserDataToUpdate)]);
                } else {
                    DB::rollback();
                    Log::warning('Forbidden: CompanyUser not found for target user in active company.', ['user_id' => $user->id, 'company_id' => $activeCompanyId]);
                    return api_not_found('المستخدم غير مرتبط بالشركة النشطة لتعديل بياناته.');
                }
            }

            // معالجة images_ids
            if ($request->has('images_ids')) {
                $imagesIds = $request->input('images_ids');
                $user->syncImages($imagesIds, 'avatar');
                Log::info('Images synced for user.', ['user_id' => $user->id, 'image_ids' => $imagesIds]);
            }
            $user->logUpdated('بتحديث المستخدم ' . ($user->activeCompanyUser->nickname_in_company ?? $user->username));
            DB::commit();
            Log::info('User update transaction committed successfully.', ['user_id' => $user->id]);

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
            DB::commit();

            return api_success(new UserResource($user), 'تم تغيير الشركة النشطة للمستخدم بنجاح.');
        } catch (Throwable $e) {
            DB::rollback();
            Log::error("فشل تغيير شركة المستخدم: " . $e->getMessage(), ['exception' => $e, 'user_id' => $authUser->id, 'target_user_id' => $user->id, 'request_data' => $request->all()]);
            return api_exception($e);
        }
    }

    /**
     * البحث عن المستخدمين بناءً على الفلاتر والصلاحيات.
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
            $isCompanyAdmin = $authUser->hasPermissionTo(perm_key('admin.company'));
            $canViewAll = $authUser->hasPermissionTo(perm_key('users.view_all'));
            $canViewChildren = $authUser->hasPermissionTo(perm_key('users.view_children'));
            $canViewSelf = $authUser->hasPermissionTo(perm_key('users.view_self'));

            $baseQuery = CompanyUser::query();

            $baseQuery->with([
                'user' => fn($q) => $q->with(['cashBoxes', 'creator', 'companies.logo']),
                'company',
            ]);

            if ($isSuperAdmin) {
                // يرى الجميع
            } elseif ($activeCompanyId) {
                $baseQuery->where('company_id', $activeCompanyId);

                if ($isCompanyAdmin || $canViewAll) {
                    // يرى الكل في شركته
                } elseif ($canViewChildren) {
                    $descendantUserIds = $authUser->getDescendantUserIds();
                    $baseQuery->whereIn('user_id', $descendantUserIds);
                } elseif ($canViewSelf) {
                    $baseQuery->where('user_id', $authUser->id);
                } else {
                    return api_forbidden('ليس لديك صلاحية للبحث عن المستخدمين في هذه الشركة.');
                }
            } else {
                return api_forbidden('ليس لديك صلاحية للبحث عن المستخدمين.');
            }

            // الإعدادات العامة للترقيم والفرز
            $perPage = max(1, $request->input('per_page', 10));
            $page = max(1, $request->input('page', 1));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $search = $request->input('search');
            $baseQueryWithoutSearch = clone $baseQuery; // نسخة بدون البحث العادي

            // الفلاتر
            if ($request->filled('search')) {
                $baseQuery->where(function ($subQuery) use ($search) {
                    $subQuery->where('nickname_in_company', 'like', '%' . $search . '%')
                        ->orWhere('full_name_in_company', 'like', '%' . $search . '%')
                        ->orWhere('user_phone', 'like', '%' . $search . '%');
                });
            }

            $baseQuery
                ->when($request->filled('nickname'), fn($q) =>
                $q->where('nickname_in_company', 'like', '%' . $request->nickname . '%'))
                ->when($request->filled('email'), fn($q) =>
                $q->whereHas('user', fn($u) =>
                $u->where('email', 'like', '%' . $request->email . '%')))
                ->when($request->filled('phone'), fn($q) =>
                $q->whereHas('user', fn($u) =>
                $u->where('phone', 'like', '%' . $request->phone . '%')))
                ->when($request->filled('status'), fn($q) =>
                $q->where('status', $request->input('status')))
                ->when($request->filled('created_at_from'), fn($q) =>
                $q->where('company_users.created_at', '>=', $request->input('created_at_from') . ' 00:00:00'))
                ->when($request->filled('created_at_to'), fn($q) =>
                $q->where('company_users.created_at', '<=', $request->input('created_at_to') . ' 23:59:59'));

            // الترتيب
            if (in_array($sortField, ['nickname_in_company', 'status', 'balance_in_company', 'position_in_company', 'customer_type_in_company', 'full_name_in_company', 'user_phone', 'user_email', 'user_username'])) {
                $baseQuery->orderBy('company_users.' . $sortField, $sortOrder);
            } elseif (in_array($sortField, ['username', 'email', 'phone'])) {
                $baseQuery->join('users', 'company_users.user_id', '=', 'users.id')
                    ->orderBy('users.' . $sortField, $sortOrder)
                    ->select('company_users.*');
            } else {
                $baseQuery->orderBy('company_users.id', $sortOrder);
            }

            // النتائج الأساسية
            $companyUsers = $baseQuery->paginate($perPage);

            // البحث الذكي في حالة عدم وجود نتائج
            if ($companyUsers->isEmpty() && $request->filled('search')) {
                $allCompanyUsers = (clone $baseQueryWithoutSearch)->limit(500)->get();

                $paginated = smart_search_paginated(
                    $allCompanyUsers,
                    $search,
                    ['nickname_in_company', 'full_name_in_company', 'user_phone'],
                    $request->query(),
                    null,
                    $perPage,
                    $page
                );

                Log::debug("✅ عدد النتائج الذكية بعد الترتيب: " . $paginated->total());

                return api_success(CompanyUserBasicResource::collection($paginated), 'تم إرجاع نتائج مقترحة بناءً على البحث.');
            }

            if ($companyUsers->isEmpty()) {
                return api_success([], 'لم يتم العثور على مستخدمين.');
            }

            return api_success(CompanyUserBasicResource::collection($companyUsers), 'تم جلب المستخدمين بنجاح.');
        } catch (Throwable $e) {
            Log::error("فشل البحث عن المستخدمين: " . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $authUser->id ?? null,
                'request_data' => $request->all()
            ]);

            return api_exception($e);
        }
    }
}

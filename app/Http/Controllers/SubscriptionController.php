<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionRequest;
use App\Http\Resources\Subscription\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubscriptionController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'creator',
            'company',
            'user', // المستخدم المشترك
            'plan', // خطة الاشتراك
        ];
    }

    /**
     * عرض قائمة الاشتراكات.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = Subscription::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // فلترة الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الاشتراكات
            } elseif ($authUser->hasAnyPermission([perm_key('subscriptions.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('subscriptions.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('subscriptions.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض الاشتراكات.');
            }

            // فلاتر الطلب
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            if ($request->filled('plan_id')) {
                $query->where('plan_id', $request->input('plan_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('starts_at_from')) {
                $query->where('starts_at', '>=', $request->input('starts_at_from') . ' 00:00:00');
            }
            if ($request->filled('starts_at_to')) {
                $query->where('starts_at', '<=', $request->input('starts_at_to') . ' 23:59:59');
            }
            if ($request->filled('ends_at_from')) {
                $query->where('ends_at', '>=', $request->input('ends_at_from') . ' 00:00:00');
            }
            if ($request->filled('ends_at_to')) {
                $query->where('ends_at', '<=', $request->input('ends_at_to') . ' 23:59:59');
            }
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // التصفح والفرز
            $perPage = max(1, (int) $request->get('per_page', 20));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');

            $subscriptions = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            return api_success($subscriptions, 'تم جلب الاشتراكات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين اشتراك جديد.
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('subscriptions.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء اشتراكات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                // التحقق من أن المستخدم والخطة ينتميان لنفس الشركة
                $user = \App\Models\User::where('id', $validatedData['user_id'])
                    ->where('company_id', $companyId)
                    ->firstOrFail();
                $plan = \App\Models\Plan::where('id', $validatedData['plan_id'])
                    ->where('company_id', $companyId)
                    ->firstOrFail();

                $subscription = Subscription::create($validatedData);
                $subscription->load($this->relations);
                DB::commit();
                return api_success(new SubscriptionResource($subscription), 'تم إنشاء الاشتراك بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين الاشتراك.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ الاشتراك.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض اشتراك محدد.
     */
    public function show(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $subscription = Subscription::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('subscriptions.view_all'), perm_key('admin.company')])) {
                $canView = $subscription->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('subscriptions.view_children'))) {
                $canView = $subscription->belongsToCurrentCompany() && $subscription->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('subscriptions.view_self'))) {
                $canView = $subscription->belongsToCurrentCompany() && $subscription->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new SubscriptionResource($subscription), 'تم استرداد الاشتراك بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذا الاشتراك.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث اشتراك محدد.
     */
    public function update(UpdateSubscriptionRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $subscription = Subscription::with(['company', 'creator', 'user', 'plan'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('subscriptions.update_all'), perm_key('admin.company')])) {
                $canUpdate = $subscription->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('subscriptions.update_children'))) {
                $canUpdate = $subscription->belongsToCurrentCompany() && $subscription->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('subscriptions.update_self'))) {
                $canUpdate = $subscription->belongsToCurrentCompany() && $subscription->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذا الاشتراك.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                // التحقق من أن المستخدم والخطة ينتميان لنفس الشركة إذا تم تغييرها
                if (isset($validatedData['user_id']) && $validatedData['user_id'] != $subscription->user_id) {
                    \App\Models\User::where('id', $validatedData['user_id'])
                        ->where('company_id', $companyId)
                        ->firstOrFail();
                }
                if (isset($validatedData['plan_id']) && $validatedData['plan_id'] != $subscription->plan_id) {
                    \App\Models\Plan::where('id', $validatedData['plan_id'])
                        ->where('company_id', $companyId)
                        ->firstOrFail();
                }

                $subscription->update($validatedData);
                $subscription->load($this->relations);
                DB::commit();
                return api_success(new SubscriptionResource($subscription), 'تم تحديث الاشتراك بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث الاشتراك.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الاشتراك.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف اشتراك محدد.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $subscription = Subscription::with(['company', 'creator'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('subscriptions.delete_all'), perm_key('admin.company')])) {
                $canDelete = $subscription->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('subscriptions.delete_children'))) {
                $canDelete = $subscription->belongsToCurrentCompany() && $subscription->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('subscriptions.delete_self'))) {
                $canDelete = $subscription->belongsToCurrentCompany() && $subscription->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذا الاشتراك.');
            }

            DB::beginTransaction();
            try {
                $deletedSubscription = $subscription->replicate();
                $deletedSubscription->setRelations($subscription->getRelations());

                $subscription->delete();
                DB::commit();
                return api_success(new SubscriptionResource($deletedSubscription), 'تم حذف الاشتراك بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف الاشتراك.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

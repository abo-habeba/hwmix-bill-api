<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Plan\PlanResource;
use App\Http\Requests\Plan\StorePlanRequest;
use App\Http\Requests\Plan\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PlanController extends Controller
{
    protected array $relations = [
        'company',
        'creator',
        'updater',
        'subscriptions',
    ];

    /**
     * عرض قائمة الخطط.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = Plan::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // فلترة الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الخطط
            } elseif ($authUser->hasAnyPermission([perm_key('plans.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('plans.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('plans.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض الخطط.');
            }

            // فلاتر الطلب
            if ($request->filled('company_id')) {
                if ($request->input('company_id') != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return api_forbidden('ليس لديك إذن لعرض الخطط لشركة أخرى.');
                }
                $query->where('company_id', $request->input('company_id'));
            }
            if ($request->filled('is_active')) {
                $query->where('is_active', (bool) $request->input('is_active'));
            }
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->input('name') . '%');
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

            $plans = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($plans->isEmpty()) {
                return api_success([], 'لم يتم العثور على خطط.');
            } else {
                return api_success(PlanResource::collection($plans), 'تم جلب الخطط بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين خطة جديدة.
     */
    public function store(StorePlanRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('plans.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء خطط.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $planCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء خطة لهذه الشركة
                if ($planCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء خطط لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }
                $validatedData['company_id'] = $planCompanyId;

                $plan = Plan::create($validatedData);
                $plan->load($this->relations);
                DB::commit();
                return api_success(new PlanResource($plan), 'تم إنشاء الخطة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين الخطة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ الخطة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض خطة محددة.
     */
    public function show(Plan $plan): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $plan->load($this->relations);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('plans.view_all'), perm_key('admin.company')])) {
                $canView = $plan->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('plans.view_children'))) {
                $canView = $plan->belongsToCurrentCompany() && $plan->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('plans.view_self'))) {
                $canView = $plan->belongsToCurrentCompany() && $plan->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new PlanResource($plan), 'تم استرداد الخطة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه الخطة.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث خطة محددة.
     */
    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $plan->load(['company', 'creator']); // تحميل العلاقات للتحقق من الصلاحيات

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('plans.update_all'), perm_key('admin.company')])) {
                $canUpdate = $plan->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('plans.update_children'))) {
                $canUpdate = $plan->belongsToCurrentCompany() && $plan->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('plans.update_self'))) {
                $canUpdate = $plan->belongsToCurrentCompany() && $plan->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذه الخطة.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                // التأكد من أن المستخدم مصرح له بتغيير company_id إذا كان سوبر أدمن
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $plan->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة الخطة إلا إذا كنت مدير عام.');
                }
                // إذا لم يتم تحديد company_id في الطلب ولكن المستخدم سوبر أدمن، لا تغير company_id الخاصة بالخطة الحالية
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }

                $plan->update($validatedData);
                $plan->load($this->relations);
                DB::commit();
                return api_success(new PlanResource($plan), 'تم تحديث الخطة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث الخطة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الخطة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف خطة محددة.
     */
    public function destroy(Plan $plan): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $plan->load(['company', 'creator', 'subscriptions']); // تحميل العلاقات للتحقق من الصلاحيات

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('plans.delete_all'), perm_key('admin.company')])) {
                $canDelete = $plan->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('plans.delete_children'))) {
                $canDelete = $plan->belongsToCurrentCompany() && $plan->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('plans.delete_self'))) {
                $canDelete = $plan->belongsToCurrentCompany() && $plan->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذه الخطة.');
            }

            DB::beginTransaction();
            try {
                // التحقق مما إذا كانت الخطة مرتبطة بأي اشتراكات
                if ($plan->subscriptions()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف الخطة. إنها مرتبطة باشتراك واحد أو أكثر.', [], 409);
                }

                $deletedPlan = $plan->replicate();
                $deletedPlan->setRelations($plan->getRelations());

                $plan->delete();
                DB::commit();
                return api_success(new PlanResource($deletedPlan), 'تم حذف الخطة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف الخطة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

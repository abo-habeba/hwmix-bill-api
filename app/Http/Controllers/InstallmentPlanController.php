<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstallmentPlan\StoreInstallmentPlanRequest;
use App\Http\Requests\InstallmentPlan\UpdateInstallmentPlanRequest;
use App\Http\Resources\InstallmentPlan\InstallmentPlanResource;
use App\Models\InstallmentPlan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InstallmentPlanController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'user',       // المستخدم الذي يخصه خطة التقسيط
            'creator',    // المستخدم الذي أنشأ خطة التقسيط
            'invoice',
            'installments',
            'company',    // يجب تحميل الشركة للتحقق من belongsToCurrentCompany
        ];
    }

    /**
     * عرض قائمة خطط التقسيط.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = InstallmentPlan::with($this->relations);
            $companyId = $authUser->company_id ?? null; // معرف الشركة النشطة للمستخدم

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع خطط التقسيط (لا توجد قيود إضافية على الاستعلام)
            } elseif ($authUser->hasAnyPermission([perm_key('installment_plans.view_all'), perm_key('admin.company')])) {
                // يرى جميع خطط التقسيط الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.view_children'))) {
                // يرى خطط التقسيط التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.view_self'))) {
                // يرى خطط التقسيط التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض خطط التقسيط.');
            }

            // التصفية بناءً على طلب المستخدم
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('invoice_id')) {
                $query->where('invoice_id', $request->input('invoice_id'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            // يمكنك إضافة المزيد من فلاتر البحث هنا
            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = (int) $request->input('limit', 20); // استخدام 'limit' كاسم للمدخل
            $sortField = $request->input('sort_by', 'created_at'); // استخدام 'created_at' كقيمة افتراضية للفرز
            $sortOrder = $request->input('sort_order', 'desc');

            $query->orderBy($sortField, $sortOrder); // تطبيق الفرز

            if ($perPage == -1) {
                // جلب كل النتائج بدون تصفح
                $plans = $query->get();
            } else {
                // جلب النتائج مع التصفح
                $plans = $query->paginate(max(1, $perPage));
            }

            if ($plans->isEmpty()) {
                return api_success($plans, 'لم يتم العثور على خطط تقسيط.');
            } else {
                return api_success($plans, 'تم جلب خطط التقسيط بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تخزين خطة تقسيط جديدة.
     *
     * @param StoreInstallmentPlanRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreInstallmentPlanRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة
            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء خطة تقسيط
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('installment_plans.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء خطط تقسيط.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // تعيين created_by و company_id تلقائيًا
                $validatedData['created_by'] = $authUser->id;
                // التأكد من أن خطة التقسيط تابعة لشركة المستخدم الحالي
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $companyId) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء خطط تقسيط لشركتك الحالية.');
                }
                $validatedData['company_id'] = $companyId; // التأكد من ربط خطة التقسيط بالشركة النشطة

                $plan = InstallmentPlan::create($validatedData);
                $plan->load($this->relations);
                DB::commit();
                return api_success(new InstallmentPlanResource($plan), 'تم إنشاء خطة التقسيط بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين خطة التقسيط.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ خطة التقسيط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * عرض خطة تقسيط محددة.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
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

            $plan = InstallmentPlan::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي خطة تقسيط
            } elseif ($authUser->hasAnyPermission([perm_key('installment_plans.view_all'), perm_key('admin.company')])) {
                // يرى إذا كانت خطة التقسيط تنتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $plan->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.view_children'))) {
                // يرى إذا كانت خطة التقسيط أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canView = $plan->belongsToCurrentCompany() && $plan->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.view_self'))) {
                // يرى إذا كانت خطة التقسيط أنشأها هو وتابعة للشركة النشطة
                $canView = $plan->belongsToCurrentCompany() && $plan->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new InstallmentPlanResource($plan), 'تم استرداد خطة التقسيط بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض خطة التقسيط هذه.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تحديث خطة تقسيط محددة.
     *
     * @param UpdateInstallmentPlanRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateInstallmentPlanRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $plan = InstallmentPlan::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي خطة تقسيط
            } elseif ($authUser->hasAnyPermission([perm_key('installment_plans.update_all'), perm_key('admin.company')])) {
                // يمكنه تعديل أي خطة تقسيط داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $plan->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.update_children'))) {
                // يمكنه تعديل خطط التقسيط التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $plan->belongsToCurrentCompany() && $plan->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.update_self'))) {
                // يمكنه تعديل خطة تقسيطه الخاصة التي أنشأها وتابعة للشركة النشطة
                $canUpdate = $plan->belongsToCurrentCompany() && $plan->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث خطة التقسيط هذه.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                $plan->update($validatedData);
                $plan->load($this->relations); // إعادة تحميل العلاقات بعد التحديث
                DB::commit();
                return api_success(new InstallmentPlanResource($plan), 'تم تحديث خطة التقسيط بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث خطة التقسيط.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث خطة التقسيط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * حذف خطة تقسيط محددة.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
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

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $plan = InstallmentPlan::with(['company', 'creator', 'installments'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('installment_plans.delete_all'), perm_key('admin.company')])) {
                // يمكنه حذف أي خطة تقسيط داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $plan->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.delete_children'))) {
                // يمكنه حذف خطط التقسيط التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $plan->belongsToCurrentCompany() && $plan->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.delete_self'))) {
                // يمكنه حذف خطة تقسيطه الخاصة التي أنشأها وتابعة للشركة النشطة
                $canDelete = $plan->belongsToCurrentCompany() && $plan->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف خطة التقسيط هذه.');
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كانت خطة التقسيط مرتبطة بأي أقساط
                if ($plan->installments()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف خطة التقسيط. إنها مرتبطة بأقساط موجودة.', [], 409);
                }

                $deletedPlan = $plan->replicate(); // نسخ الكائن قبل الحذف
                $deletedPlan->setRelations($plan->getRelations()); // نسخ العلاقات المحملة

                $plan->delete();
                DB::commit();
                return api_success(new InstallmentPlanResource($deletedPlan), 'تم حذف خطة التقسيط بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف خطة التقسيط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}

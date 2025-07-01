<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InstallmentPlan;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Throwable; // تم إضافة هذا الاستيراد
use Illuminate\Support\Facades\Log; // تم إضافة هذا الاستيراد
use App\Http\Resources\InstallmentPlan\InstallmentPlanResource;
use App\Http\Requests\InstallmentPlan\StoreInstallmentPlanRequest;
use App\Http\Requests\InstallmentPlan\UpdateInstallmentPlanRequest;


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
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();
            // $authUser = $request->user();
            if (!$authUser) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not authenticated.'], 401);
            }
            $query = InstallmentPlan::with($this->relations);

            $companyId = $authUser->company_id; // معرف الشركة النشطة للمستخدم
            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
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
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view installment plans.'], 403);
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

            // الترتيب
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc'); // عادة ما يكون الأحدث أولاً
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');

            // التصفحة
            $perPage = (int) $request->get('limit', 20);
            $plans = $query->paginate($perPage);

            return InstallmentPlanResource::collection($plans)->additional([
                'total' => $plans->total(),
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Installment Plan index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error retrieving installment plans.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreInstallmentPlanRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreInstallmentPlanRequest $request)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة
            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // صلاحيات إنشاء خطة تقسيط
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('installment_plans.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to create installment plans.'
                ], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // تعيين created_by و company_id تلقائيًا
                $validatedData['created_by'] = $authUser->id;
                // التأكد من أن خطة التقسيط تابعة لشركة المستخدم الحالي
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $companyId) {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You can only create installment plans for your current company.'], 403);
                }
                $validatedData['company_id'] = $companyId; // التأكد من ربط خطة التقسيط بالشركة النشطة

                $plan = InstallmentPlan::create($validatedData);
                $plan->load($this->relations);
                DB::commit();
                Log::info('Installment Plan created successfully.', ['plan_id' => $plan->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new InstallmentPlanResource($plan);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Installment Plan store failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'error' => 'Error saving installment plan.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Installment Plan store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error saving installment plan.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\InstallmentPlan\InstallmentPlanResource
     */
    public function show($id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
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
            } else {
                // لا توجد صلاحية عرض
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this installment plan.'], 403);
            }

            if ($canView) {
                return new InstallmentPlanResource($plan);
            }

            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this installment plan.'], 403);
        } catch (Throwable $e) {
            Log::error('Installment Plan show failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'plan_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error retrieving installment plan.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateInstallmentPlanRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\InstallmentPlan\InstallmentPlanResource
     */
    public function update(UpdateInstallmentPlanRequest $request, $id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
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
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this installment plan.'], 403);
            }

            if (!$canUpdate) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this installment plan.'], 403);
            }

            DB::beginTransaction();
            try {
                $plan->update($request->validated());
                $plan->load($this->relations); // إعادة تحميل العلاقات بعد التحديث
                DB::commit();
                Log::info('Installment Plan updated successfully.', ['plan_id' => $plan->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new InstallmentPlanResource($plan);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Installment Plan update failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'plan_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'error' => 'Error updating installment plan.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Installment Plan update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'plan_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error updating installment plan.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $plan = InstallmentPlan::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true; // المسؤول العام يمكنه حذف أي خطة تقسيط
            } elseif ($authUser->hasAnyPermission([perm_key('installment_plans.delete_all'), perm_key('admin.company')])) {
                // يمكنه حذف أي خطة تقسيط داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $plan->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.delete_children'))) {
                // يمكنه حذف خطط التقسيط التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $plan->belongsToCurrentCompany() && $plan->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_plans.delete_self'))) {
                // يمكنه حذف خطة تقسيطه الخاصة التي أنشأها وتابعة للشركة النشطة
                $canDelete = $plan->belongsToCurrentCompany() && $plan->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this installment plan.'], 403);
            }

            if (!$canDelete) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this installment plan.'], 403);
            }

            DB::beginTransaction();
            try {
                $plan->delete();
                DB::commit();
                Log::info('Installment Plan deleted successfully.', ['plan_id' => $id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(['message' => 'Deleted successfully'], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Installment Plan deletion failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'plan_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'error' => 'Error deleting installment plan.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Installment Plan deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'plan_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error deleting installment plan.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}

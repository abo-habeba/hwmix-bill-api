<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installment\StoreInstallmentRequest;
use App\Http\Requests\Installment\UpdateInstallmentRequest;
use App\Http\Resources\Installment\InstallmentResource;
use App\Models\Installment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InstallmentController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'installmentPlan',
            'user',      // المستخدم الذي يخصه القسط
            'creator',   // المستخدم الذي أنشأ القسط
            'payments',
            'company',   // يجب تحميل الشركة للتحقق من belongsToCurrentCompany
        ];
    }

    /**
     * عرض قائمة بالأقساط.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = Installment::with($this->relations);
            $companyId = $authUser->company_id ?? null; // معرف الشركة النشطة للمستخدم

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الأقساط (لا توجد قيود إضافية على الاستعلام)
            } elseif ($authUser->hasAnyPermission([perm_key('installments.view_all'), perm_key('admin.company')])) {
                // يرى جميع الأقساط الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.view_children'))) {
                // يرى الأقساط التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.view_self'))) {
                // يرى الأقساط التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض الأقساط.');
            }

            // التصفية بناءً على طلب المستخدم
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('due_date_from')) {
                $query->where('due_date', '>=', $request->input('due_date_from'));
            }
            if ($request->filled('due_date_to')) {
                $query->where('due_date', '<=', $request->input('due_date_to'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            if ($request->filled('invoice_id')) {
                $query->where('invoice_id', $request->input('invoice_id'));
            }
            // يمكنك إضافة المزيد من فلاتر البحث هنا

            // الترتيب
            $sortBy = $request->get('sort_by', 'due_date');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');

            // التصفحة
            $perPage = (int) $request->get('limit', 20);
            $installments = $query->paginate($perPage);

            return api_success($installments, 'تم استرداد الأقساط بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreInstallmentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreInstallmentRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة
            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء قسط
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('installments.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء أقساط.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // تعيين created_by و company_id تلقائيًا
                $validatedData['created_by'] = $authUser->id;
                // التأكد من أن القسط تابع لشركة المستخدم الحالي
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $companyId) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء أقساط لشركتك الحالية.');
                }
                $validatedData['company_id'] = $companyId; // التأكد من ربط القسط بالشركة النشطة

                $installment = Installment::create($validatedData);
                $installment->load($this->relations);
                DB::commit();
                return api_success(new InstallmentResource($installment), 'تم إنشاء القسط بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين القسط.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ القسط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
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

            $installment = Installment::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي قسط
            } elseif ($authUser->hasAnyPermission([perm_key('installments.view_all'), perm_key('admin.company')])) {
                // يرى إذا كان القسط ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $installment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.view_children'))) {
                // يرى إذا كان القسط أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $installment->belongsToCurrentCompany() && $installment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.view_self'))) {
                // يرى إذا كان القسط أنشأه هو وتابع للشركة النشطة
                $canView = $installment->belongsToCurrentCompany() && $installment->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new InstallmentResource($installment), 'تم استرداد القسط بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذا القسط.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateInstallmentRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateInstallmentRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $installment = Installment::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي قسط
            } elseif ($authUser->hasAnyPermission([perm_key('installments.update_all'), perm_key('admin.company')])) {
                // يمكنه تعديل أي قسط داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $installment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.update_children'))) {
                // يمكنه تعديل الأقساط التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $installment->belongsToCurrentCompany() && $installment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.update_self'))) {
                // يمكنه تعديل قسطه الخاص الذي أنشأه وتابع للشركة النشطة
                $canUpdate = $installment->belongsToCurrentCompany() && $installment->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذا القسط.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                $installment->update($validatedData);
                $installment->load($this->relations); // إعادة تحميل العلاقات بعد التحديث
                DB::commit();
                return api_success(new InstallmentResource($installment), 'تم تحديث القسط بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث القسط.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث القسط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
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
            $installment = Installment::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('installments.delete_all'), perm_key('admin.company')])) {
                // يمكنه حذف أي قسط داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $installment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.delete_children'))) {
                // يمكنه حذف الأقساط التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $installment->belongsToCurrentCompany() && $installment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.delete_self'))) {
                // يمكنه حذف قسطه الخاص الذي أنشأه وتابع للشركة النشطة
                $canDelete = $installment->belongsToCurrentCompany() && $installment->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذا القسط.');
            }

            DB::beginTransaction();
            try {
                // حفظ نسخة من القسط قبل حذفه لإرجاعها في الاستجابة
                $deletedInstallment = $installment->replicate();
                $deletedInstallment->setRelations($installment->getRelations()); // نسخ العلاقات المحملة

                $installment->delete();
                DB::commit();
                return api_success(new InstallmentResource($deletedInstallment), 'تم حذف القسط بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف القسط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}

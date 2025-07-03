<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstallmentPaymentDetail\StoreInstallmentPaymentDetailRequest;
use App\Http\Requests\InstallmentPaymentDetail\UpdateInstallmentPaymentDetailRequest;
use App\Http\Resources\InstallmentPaymentDetail\InstallmentPaymentDetailResource;
use App\Models\InstallmentPaymentDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InstallmentPaymentDetailController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'installmentPayment', // العلاقة مع InstallmentPayment
            'installment',        // العلاقة مع Installment
            'company',            // للتحقق من belongsToCurrentCompany
            'creator',            // للتحقق من createdByCurrentUser/OrChildren
        ];
    }

    /**
     * عرض قائمة تفاصيل دفعات الأقساط.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = InstallmentPaymentDetail::with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع التفاصيل
            } elseif ($authUser->hasAnyPermission([perm_key('installment_payment_details.view_all'), perm_key('admin.company')])) {
                // يرى جميع التفاصيل الخاصة بالشركة النشطة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_payment_details.view_children'))) {
                // يرى التفاصيل التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_payment_details.view_self'))) {
                // يرى التفاصيل التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض تفاصيل دفعات الأقساط.');
            }

            // التصفية بناءً على طلب المستخدم (يمكن إضافة المزيد هنا)
            if ($request->filled('installment_payment_id')) {
                $query->where('installment_payment_id', $request->input('installment_payment_id'));
            }
            if ($request->filled('installment_id')) {
                $query->where('installment_id', $request->input('installment_id'));
            }
            if ($request->filled('amount')) {
                $query->where('amount', $request->input('amount'));
            }

            $perPage = max(1, (int) $request->get('limit', 20));
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $details = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            if ($details->isEmpty()) {
                return api_success($details, 'لم يتم العثور على تفاصيل دفعات أقساط.');
            } else {
                return api_success($details, 'تم جلب تفاصيل دفعات الأقساط بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تخزين تفاصيل دفعة قسط جديدة.
     *
     * @param StoreInstallmentPaymentDetailRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreInstallmentPaymentDetailRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('installment_payment_details.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء تفاصيل دفعات الأقساط.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;
                $validatedData['company_id'] = $companyId; // ربط بتفاصيل الشركة الحالية

                $detail = InstallmentPaymentDetail::create($validatedData);
                $detail->load($this->relations);
                DB::commit();
                return api_success(new InstallmentPaymentDetailResource($detail), 'تم إنشاء تفاصيل دفعة القسط بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين تفاصيل دفعة القسط.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ تفاصيل دفعة القسط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * عرض تفاصيل دفعة قسط محددة.
     *
     * @param InstallmentPaymentDetail $installmentPaymentDetail
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(InstallmentPaymentDetail $installmentPaymentDetail): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $installmentPaymentDetail->load($this->relations);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('installment_payment_details.view_all'), perm_key('admin.company')])) {
                $canView = $installmentPaymentDetail->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_payment_details.view_children'))) {
                $canView = $installmentPaymentDetail->belongsToCurrentCompany() && $installmentPaymentDetail->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_payment_details.view_self'))) {
                $canView = $installmentPaymentDetail->belongsToCurrentCompany() && $installmentPaymentDetail->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new InstallmentPaymentDetailResource($installmentPaymentDetail), 'تم استرداد تفاصيل دفعة القسط بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض تفاصيل دفعة القسط هذه.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تحديث تفاصيل دفعة قسط محددة.
     *
     * @param UpdateInstallmentPaymentDetailRequest $request
     * @param InstallmentPaymentDetail $installmentPaymentDetail
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateInstallmentPaymentDetailRequest $request, InstallmentPaymentDetail $installmentPaymentDetail): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $installmentPaymentDetail->load(['company', 'creator']); // تحميل العلاقات للتحقق من الصلاحيات

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('installment_payment_details.update_all'), perm_key('admin.company')])) {
                $canUpdate = $installmentPaymentDetail->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_payment_details.update_children'))) {
                $canUpdate = $installmentPaymentDetail->belongsToCurrentCompany() && $installmentPaymentDetail->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_payment_details.update_self'))) {
                $canUpdate = $installmentPaymentDetail->belongsToCurrentCompany() && $installmentPaymentDetail->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث تفاصيل دفعة القسط هذه.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                $installmentPaymentDetail->update($validatedData);
                $installmentPaymentDetail->load($this->relations);
                DB::commit();
                return api_success(new InstallmentPaymentDetailResource($installmentPaymentDetail), 'تم تحديث تفاصيل دفعة القسط بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث تفاصيل دفعة القسط.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث تفاصيل دفعة القسط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * حذف تفاصيل دفعة قسط محددة.
     *
     * @param InstallmentPaymentDetail $installmentPaymentDetail
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(InstallmentPaymentDetail $installmentPaymentDetail): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $installmentPaymentDetail->load(['company', 'creator']); // تحميل العلاقات للتحقق من الصلاحيات

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('installment_payment_details.delete_all'), perm_key('admin.company')])) {
                $canDelete = $installmentPaymentDetail->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_payment_details.delete_children'))) {
                $canDelete = $installmentPaymentDetail->belongsToCurrentCompany() && $installmentPaymentDetail->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installment_payment_details.delete_self'))) {
                $canDelete = $installmentPaymentDetail->belongsToCurrentCompany() && $installmentPaymentDetail->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف تفاصيل دفعة القسط هذه.');
            }

            DB::beginTransaction();
            try {
                // لا يوجد فحص إضافي للعلاقات هنا، حيث أن تفاصيل الدفع هي عادةً سجلات نهائية.
                // إذا كانت هناك علاقات أخرى تمنع الحذف، يجب إضافتها هنا.

                $deletedDetail = $installmentPaymentDetail->replicate();
                $deletedDetail->setRelations($installmentPaymentDetail->getRelations());

                $installmentPaymentDetail->delete();
                DB::commit();
                return api_success(new InstallmentPaymentDetailResource($deletedDetail), 'تم حذف تفاصيل دفعة القسط بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف تفاصيل دفعة القسط.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}

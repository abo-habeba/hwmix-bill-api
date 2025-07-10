<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installment\StoreInstallmentRequest;
use App\Http\Requests\Installment\UpdateInstallmentRequest;
use App\Http\Requests\InstallmentPayment\PayInstallmentsRequest;
use App\Http\Resources\Installment\InstallmentResource;
use App\Models\Installment;
use App\Services\InstallmentPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InstallmentPaymentController extends Controller
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
            $companyId = $authUser->company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الأقساط
            } elseif ($authUser->hasAnyPermission([perm_key('installments.view_all'), perm_key('admin.company')])) {
                // يرى جميع الأقساط الخاصة بالشركة النشطة
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

            // التصفية بناءً على طلب المستخدم (يمكن إضافة المزيد هنا)
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

            $perPage = max(1, (int) $request->get('limit', 20));
            $sortBy = $request->get('sort_by', 'due_date');
            $sortOrder = $request->get('sort_order', 'asc');
            $installments = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            if ($installments->isEmpty()) {
                return api_success([], 'لم يتم العثور على أقساط.');
            } else {
                return api_success(InstallmentResource::collection($installments), 'تم جلب الأقساط بنجاح.');
            }
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

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('installments.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء أقساط.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $companyId) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء أقساط لشركتك الحالية.');
                }
                $validatedData['company_id'] = $companyId;

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

            $installment = Installment::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('installments.view_all'), perm_key('admin.company')])) {
                $canView = $installment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.view_children'))) {
                $canView = $installment->belongsToCurrentCompany() && $installment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.view_self'))) {
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
     * @param string $id
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

            $installment = Installment::with(['company', 'creator'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('installments.update_all'), perm_key('admin.company')])) {
                $canUpdate = $installment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.update_children'))) {
                $canUpdate = $installment->belongsToCurrentCompany() && $installment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.update_self'))) {
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
                $installment->load($this->relations);
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

            $installment = Installment::with(['company', 'creator'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('installments.delete_all'), perm_key('admin.company')])) {
                $canDelete = $installment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.delete_children'))) {
                $canDelete = $installment->belongsToCurrentCompany() && $installment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.delete_self'))) {
                $canDelete = $installment->belongsToCurrentCompany() && $installment->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذا القسط.');
            }

            DB::beginTransaction();
            try {
                $deletedInstallment = $installment->replicate();
                $deletedInstallment->setRelations($installment->getRelations());

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

    /**
     * دفع الأقساط.
     *
     * @param PayInstallmentsRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function payInstallments(PayInstallmentsRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحية دفع الأقساط
            if (
                !$authUser->hasPermissionTo(perm_key('admin.super')) &&
                !$authUser->hasPermissionTo(perm_key('installments.pay')) &&
                !$authUser->hasPermissionTo(perm_key('admin.company'))
            ) {
                return api_forbidden('ليس لديك إذن لدفع الأقساط.');
            }

            $validatedData = $request->validated();

            $cashBoxId = $validatedData['cash_box_id'] ?? $authUser->cashBoxeDefault?->id;
            if (!$cashBoxId) {
                return api_error('لم يتم العثور على صندوق نقدي افتراضي للمستخدم.', [], 400);
            }

            // التحقق من أن الصندوق النقدي المختار ينتمي لشركة المستخدم
            $cashBox = \App\Models\CashBox::find($cashBoxId);
            if (
                !$cashBox ||
                (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)
            ) {
                return api_forbidden('صندوق النقد المحدد غير صالح أو غير متاح لشركتك.');
            }

            DB::beginTransaction();
            try {
                $service = new InstallmentPaymentService();
                $service->payInstallments(
                    $validatedData['installment_ids'],
                    $validatedData['amount'],
                    [
                        'user_id' => $validatedData['user_id'],
                        'installment_plan_id' => $validatedData['installment_plan_id'],
                        'payment_method_id' => $validatedData['payment_method_id'],
                        'cash_box_id' => $cashBoxId,
                        'notes' => $validatedData['notes'] ?? '',
                        'paid_at' => $validatedData['paid_at'] ?? now(),
                        'amount' => $validatedData['amount'],
                    ]
                );

                $updatedInstallments = Installment::whereIn('id', $validatedData['installment_ids'])->with($this->relations)->get();

                DB::commit();
                return api_success(InstallmentResource::collection($updatedInstallments), 'تم دفع الأقساط بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء دفع الأقساط.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error(
                    'حدث خطأ أثناء دفع الأقساط.',
                    [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => config('app.debug') ? collect($e->getTrace())->take(5) : [],
                    ],
                    500
                );
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}

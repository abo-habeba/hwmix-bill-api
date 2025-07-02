<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceType;
use App\Services\ServiceResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'user',
            'company',
            'invoiceType',
            'items',
            'installmentPlan',
            'creator', // للمصادقة على createdByUser/OrChildren
        ];
    }

    /**
     * عرض قائمة بالفواتير.
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

            $query = Invoice::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة أو لديك صلاحية مدير عام.');
            }

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الفواتير (لا توجد قيود إضافية على الاستعلام)
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض الفواتير.');
            }

            // فلاتر الطلب الإضافية
            if ($request->filled('invoice_type_id')) {
                $query->where('invoice_type_id', $request->input('invoice_type_id'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            // فلاتر التاريخ
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, (int) $request->input('per_page', 20));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');

            $invoices = $query
                ->orderBy($sortField, $sortOrder)
                ->paginate($perPage);

            return api_success($invoices, 'تم جلب الفواتير بنجاح');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين فاتورة جديدة في قاعدة البيانات.
     *
     * @param StoreInvoiceRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('invoices.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك صلاحية لإنشاء الفواتير.');
            }

            $validated = $request->validated();
            $validated['company_id'] = $companyId;
            $validated['created_by'] = $authUser->id;

            DB::beginTransaction();
            try {
                $invoiceType = InvoiceType::findOrFail($validated['invoice_type_id']);
                $invoiceTypeCode = $validated['invoice_type_code'] ?? $invoiceType->code;

                $serviceResolver = new ServiceResolver();
                $service = $serviceResolver->resolve($invoiceTypeCode);

                $responseDTO = $service->create($validated); // الخدمة يجب أن ترجع كائن Invoice

                $responseDTO->load($this->relations); // تحميل العلاقات بعد الإنشاء
                DB::commit();
                return api_success(new InvoiceResource($responseDTO), 'تم إنشاء المستند بنجاح', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء إنشاء المستند.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء إنشاء المستند.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض الفاتورة المحددة.
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

            $invoice = Invoice::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.view_all'), perm_key('admin.company')])) {
                $canView = $invoice->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_children'))) {
                $canView = $invoice->belongsToCurrentCompany() && $invoice->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_self'))) {
                $canView = $invoice->belongsToCurrentCompany() && $invoice->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new InvoiceResource($invoice), 'تم جلب بيانات الفاتورة بنجاح');
            }

            return api_forbidden('ليس لديك صلاحية لعرض هذه الفاتورة.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث الفاتورة المحددة في قاعدة البيانات.
     *
     * @param UpdateInvoiceRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateInvoiceRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $invoice = Invoice::with(['company', 'creator'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.update_all'), perm_key('admin.company')])) {
                $canUpdate = $invoice->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.update_children'))) {
                $canUpdate = $invoice->belongsToCurrentCompany() && $invoice->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.update_self'))) {
                $canUpdate = $invoice->belongsToCurrentCompany() && $invoice->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك صلاحية لتحديث هذه الفاتورة.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id; // تعيين من قام بالتعديل

                $invoice->update($validatedData);
                $invoice->load($this->relations); // إعادة تحميل العلاقات بعد التحديث
                DB::commit();
                return api_success(new InvoiceResource($invoice), 'تم تحديث الفاتورة بنجاح');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث المستند.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث المستند.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف الفاتورة المحددة من قاعدة البيانات.
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

            $invoice = Invoice::with(['company', 'creator'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.delete_all'), perm_key('admin.company')])) {
                $canDelete = $invoice->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.delete_children'))) {
                $canDelete = $invoice->belongsToCurrentCompany() && $invoice->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.delete_self'))) {
                $canDelete = $invoice->belongsToCurrentCompany() && $invoice->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك صلاحية لحذف هذه الفاتورة.');
            }

            DB::beginTransaction();
            try {
                // حفظ نسخة من الفاتورة قبل حذفها لإرجاعها في الاستجابة
                $deletedInvoice = $invoice->replicate();
                $deletedInvoice->setRelations($invoice->getRelations());

                $invoice->delete();
                DB::commit();
                return api_success(new InvoiceResource($deletedInvoice), 'تم حذف الفاتورة بنجاح');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف المستند.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

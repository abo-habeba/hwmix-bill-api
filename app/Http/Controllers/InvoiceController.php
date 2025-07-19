<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Invoice;
use App\Models\InvoiceType;
use Illuminate\Http\Request;
use App\Services\ServiceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceItem\InvoiceItemResource;
use Illuminate\Support\Collection;

class InvoiceController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'user.cashBoxeDefault',
            'company',
            'invoiceType',
            'items.variant',
            'installmentPlan',
            'creator',
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

            // إضافة صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // لا قيود
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
            // فلتر الحالة: استثناء الملغاة افتراضياً ما لم يتم طلبها صراحةً
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            } else {
                $query->where('status', '!=', 'canceled');
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

            $invoices = $query->orderByRaw('GREATEST(updated_at, created_at) DESC')->paginate($perPage);

            if ($invoices->isEmpty()) {
                return api_success([], 'لم يتم العثور على فواتير.');
            } else {
                return api_success(InvoiceResource::Collection($invoices), 'تم جلب الفواتير بنجاح.');
            }
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

            // إضافة صلاحية الإنشاء
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

                $responseDTO = $service->create($validated);

                if (!$responseDTO || !$responseDTO instanceof \App\Models\Invoice) {
                    \Log::error('لم يتم إنشاء الفاتورة بنجاح من الـ Service', [
                        'returned_value' => $responseDTO,
                        'invoice_type_code' => $invoiceTypeCode,
                        'validated_data' => $validated,
                    ]);
                    throw new \Exception('فشل إنشاء الفاتورة من الخدمة.');
                }

                $responseDTO->load($this->relations);
                DB::commit();
                return api_success(new InvoiceResource($responseDTO), 'تم إنشاء المستند بنجاح', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء إنشاء المستند.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
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
            // إضافة صلاحيات العرض
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
     * @param Invoice $invoice
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الانتماء لشركة.');
            }

            $canUpdate = false;
            // إضافة صلاحيات التعديل
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
                return api_forbidden('ليس لديك صلاحية لتعديل هذه الفاتورة.');
            }

            DB::beginTransaction();
            try {
                $validated = $request->validated();
                $validated['company_id'] = $companyId;
                $validated['updated_by'] = $authUser->id;

                $invoiceTypeCode = $invoice->invoice_type_code;
                $service = ServiceResolver::resolve($invoiceTypeCode);

                $updatedInvoice = $service->update($validated, $invoice);
                $updatedInvoice->load($this->relations);

                DB::commit();
                return api_success(new InvoiceResource($updatedInvoice), 'تم تعديل الفاتورة بنجاح');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('خطأ في التحقق من البيانات', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
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
        DB::beginTransaction(); // بدء المعاملة لضمان اتساق البيانات
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $invoice = Invoice::with(['company', 'creator'])->findOrFail($id);

            // صلاحيات الحذف (يمكن تبسيطها أكثر داخل سياسات Laravel)
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

            $invoiceTypeCode = $invoice->invoice_type_code; // الحصول على نوع الفاتورة
            $service = ServiceResolver::resolve($invoiceTypeCode); // حل الخدمة المناسبة لنوع الفاتورة

            $canceledInvoice = $service->cancel($invoice); // تفويض الحذف للخدمة

            DB::commit(); // تأكيد المعاملة
            return api_success(new InvoiceResource($canceledInvoice), 'تم حذف الفاتورة بنجاح');
        } catch (Throwable $e) {
            DB::rollBack(); // التراجع عن المعاملة في حالة حدوث أي خطأ
            return api_exception($e);
        }
    }
}

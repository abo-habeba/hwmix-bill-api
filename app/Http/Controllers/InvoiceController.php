<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;  // تم تصحيح الخطأ هنا
use App\Http\Resources\Invoice\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceType;
use App\Services\ServiceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;  // تم تصحيح الخطأ هنا
use Illuminate\Validation\ValidationException;
use Throwable;  // استخدام Throwable لشمولية أكبر في التقاط الأخطاء

class InvoiceController extends Controller
{
    /**
     * عرض قائمة بالفواتير.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();
            $query = Invoice::query();
            $companyId = $authUser->company_id;  // معرف الشركة النشطة للمستخدم

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الفواتير (لا توجد قيود إضافية على الاستعلام)
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.view_all'), perm_key('admin.company')])) {
                // يرى جميع الفواتير الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_children'))) {
                // يرى الفواتير التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_self'))) {
                // يرى الفواتير التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
            }

            // فلاتر الطلب الإضافية (يمكن إضافة المزيد هنا)
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
            $perPage = max(1, $request->input('per_page', 20));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');  // الفواتير عادة ما تكون بترتيب تنازلي

            $invoices = $query
                ->with(['user', 'company', 'invoiceType', 'items', 'installmentPlan'])
                ->orderBy($sortField, $sortOrder)
                ->paginate($perPage);

            return InvoiceResource::collection($invoices)->additional([
                'total' => $invoices->total(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Invoice index failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'Error retrieving invoices.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * تخزين فاتورة جديدة في قاعدة البيانات.
     *
     * @param StoreInvoiceRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreInvoiceRequest $request)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            // التحقق من صلاحية إنشاء الفواتير
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('invoices.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to create invoices.'], 403);
            }

            // التأكد من أن المستخدم لديه شركة نشطة لربط الفاتورة بها
            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company to create invoices.'], 403);
            }

            $validated = $request->validated();

            // التأكد من أن الفاتورة تُنشأ للشركة الحالية للمستخدم
            // إذا كان company_id مُرسلاً في الطلب، فيجب أن يتطابق مع company_id للمستخدم
            if (isset($validated['company_id']) && $validated['company_id'] != $companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You can only create invoices for your current company.'], 403);
            }
            // إسناد company_id للمستخدم إذا لم يكن موجودًا في الطلب
            $validated['company_id'] = $companyId;
            $validated['created_by'] = $authUser->id;  // تسجيل من قام بإنشاء الفاتورة

            $invoiceType = InvoiceType::findOrFail($validated['invoice_type_id']);
            $invoiceTypeCode = $validated['invoice_type_code'] ?? $invoiceType->code;

            $serviceResolver = new ServiceResolver();
            $service = $serviceResolver->resolve($invoiceTypeCode);

            $responseDTO = DB::transaction(function () use ($service, $validated) {
                return $service->create($validated);
            });

            Log::info('Invoice created successfully.', ['invoice_id' => $responseDTO->id ?? null, 'user_id' => $authUser->id, 'company_id' => $companyId]);

            return response()->json([
                'status' => 'success',
                'message' => 'تم إنشاء المستند بنجاح',
                'data' => new InvoiceResource($responseDTO),  // استخدم Resource هنا
            ], 201);
        } catch (ValidationException $e) {
            Log::error('Invoice store validation failed: ' . $e->getMessage(), ['errors' => $e->errors(), 'user_id' => Auth::id()]);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Invoice store failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString(), 'user_id' => Auth::id()]);
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء إنشاء المستند',
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }

    /**
     * عرض الفاتورة المحددة.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Invoice\InvoiceResource
     */
    public function show($id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة
            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            $invoice = Invoice::with(['user', 'company', 'invoiceType', 'items', 'installmentPlan'])->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;  // المسؤول العام يرى أي فاتورة
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.view_all'), perm_key('admin.company')])) {
                // يرى إذا كانت الفاتورة تنتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $invoice->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_children'))) {
                // يرى إذا كانت الفاتورة أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canView = $invoice->belongsToCurrentCompany() && $invoice->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.view_self'))) {
                // يرى إذا كانت الفاتورة أنشأها هو وتابعة للشركة النشطة
                $canView = $invoice->belongsToCurrentCompany() && $invoice->createdByCurrentUser();
            } else {
                // لا توجد صلاحية عرض
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this invoice.'], 403);
            }

            if ($canView) {
                return new InvoiceResource($invoice);
            }

            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this invoice.'], 403);
        } catch (Throwable $e) {
            Log::error('Invoice show failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString(), 'invoice_id' => $id, 'user_id' => Auth::id()]);
            return response()->json([
                'error' => 'Error retrieving invoice.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * تحديث الفاتورة المحددة في قاعدة البيانات.
     *
     * @param UpdateInvoiceRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Invoice\InvoiceResource
     */
    public function update(UpdateInvoiceRequest $request, $id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            $invoice = Invoice::with(['company'])->findOrFail($id);  // جلب الشركة للتحقق منها

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;  // المسؤول العام يمكنه تعديل أي فاتورة
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.update_any'), perm_key('admin.company')])) {
                // يمكنه تعديل أي فاتورة داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $invoice->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.update_children'))) {
                // يمكنه تعديل الفواتير التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $invoice->belongsToCurrentCompany() && $invoice->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.update_self'))) {
                // يمكنه تعديل فاتورته الخاصة التي أنشأها وتابعة للشركة النشطة
                $canUpdate = $invoice->belongsToCurrentCompany() && $invoice->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this invoice.'], 403);
            }

            if (!$canUpdate) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this invoice.'], 403);
            }

            DB::beginTransaction();
            try {
                $invoice->update($request->validated());
                $invoice->load(['user', 'company', 'invoiceType', 'items', 'installmentPlan']);  // إعادة تحميل العلاقات بعد التحديث
                DB::commit();
                Log::info('Invoice updated successfully.', ['invoice_id' => $invoice->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new InvoiceResource($invoice);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Invoice update failed in transaction: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString(), 'invoice_id' => $id, 'user_id' => Auth::id()]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'حدث خطأ أثناء تحديث المستند',
                    'error' => [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Invoice update failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString(), 'invoice_id' => $id, 'user_id' => Auth::id()]);
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء تحديث المستند',
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }

    /**
     * حذف الفاتورة المحددة من قاعدة البيانات.
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

            $invoice = Invoice::with(['company'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;  // المسؤول العام يمكنه حذف أي فاتورة
            } elseif ($authUser->hasAnyPermission([perm_key('invoices.delete_any'), perm_key('admin.company')])) {
                // يمكنه حذف أي فاتورة داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $invoice->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.delete_children'))) {
                // يمكنه حذف الفواتير التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $invoice->belongsToCurrentCompany() && $invoice->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoices.delete_self'))) {
                // يمكنه حذف فاتورته الخاصة التي أنشأها وتابعة للشركة النشطة
                $canDelete = $invoice->belongsToCurrentCompany() && $invoice->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this invoice.'], 403);
            }

            if (!$canDelete) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this invoice.'], 403);
            }

            DB::beginTransaction();
            try {
                $invoice->delete();
                DB::commit();
                Log::info('Invoice deleted successfully.', ['invoice_id' => $id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(['message' => 'Deleted successfully'], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Invoice deletion failed in transaction: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString(), 'invoice_id' => $id, 'user_id' => Auth::id()]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'حدث خطأ أثناء حذف المستند',
                    'error' => [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Invoice deletion failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString(), 'invoice_id' => $id, 'user_id' => Auth::id()]);
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء حذف المستند',
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }
}

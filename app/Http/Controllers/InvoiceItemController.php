<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceItem\StoreInvoiceItemRequest;
use App\Http\Requests\InvoiceItem\UpdateInvoiceItemRequest;
use App\Http\Resources\InvoiceItem\InvoiceItemResource;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceItemController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'invoice',   // العلاقة مع الفاتورة
            'product',   // العلاقة مع المنتج (إذا كانت موجودة)
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
        ];
    }

    /**
     * عرض قائمة عناصر الفواتير.
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

            $query = InvoiceItem::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع عناصر الفواتير
            } elseif ($authUser->hasAnyPermission([perm_key('invoice_items.view_all'), perm_key('admin.company')])) {
                // يرى جميع عناصر الفواتير الخاصة بالشركة النشطة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_items.view_children'))) {
                // يرى عناصر الفواتير التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_items.view_self'))) {
                // يرى عناصر الفواتير التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض عناصر الفواتير.');
            }

            // فلاتر الطلب الإضافية
            if ($request->filled('invoice_id')) {
                $query->where('invoice_id', $request->input('invoice_id'));
            }
            if ($request->filled('product_id')) {
                $query->where('product_id', $request->input('product_id'));
            }
            if ($request->filled('quantity')) {
                $query->where('quantity', $request->input('quantity'));
            }
            if ($request->filled('price')) {
                $query->where('price', $request->input('price'));
            }
            if ($request->filled('total_price')) {
                $query->where('total_price', $request->input('total_price'));
            }
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

            $items = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            return api_success($items, 'تم استرداد عناصر الفواتير بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين عنصر فاتورة جديد.
     *
     * @param StoreInvoiceItemRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreInvoiceItemRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('invoice_items.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك صلاحية لإنشاء عناصر فواتير.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;
                $validatedData['company_id'] = $companyId;

                // التحقق من أن الفاتورة المرتبطة تنتمي للشركة الحالية
                $invoice = \App\Models\Invoice::where('id', $validatedData['invoice_id'])
                    ->where('company_id', $companyId)
                    ->firstOrFail();

                $item = InvoiceItem::create($validatedData);
                $item->load($this->relations);
                DB::commit();
                return api_success(new InvoiceItemResource($item), 'تم إنشاء عنصر الفاتورة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين عنصر الفاتورة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ عنصر الفاتورة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض عنصر فاتورة محدد.
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

            $item = InvoiceItem::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoice_items.view_all'), perm_key('admin.company')])) {
                $canView = $item->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_items.view_children'))) {
                $canView = $item->belongsToCurrentCompany() && $item->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_items.view_self'))) {
                $canView = $item->belongsToCurrentCompany() && $item->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new InvoiceItemResource($item), 'تم استرداد عنصر الفاتورة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض عنصر الفاتورة هذا.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث عنصر فاتورة محدد.
     *
     * @param UpdateInvoiceItemRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateInvoiceItemRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $item = InvoiceItem::with(['company', 'creator'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoice_items.update_all'), perm_key('admin.company')])) {
                $canUpdate = $item->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_items.update_children'))) {
                $canUpdate = $item->belongsToCurrentCompany() && $item->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_items.update_self'))) {
                $canUpdate = $item->belongsToCurrentCompany() && $item->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث عنصر الفاتورة هذا.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                // التحقق من أن الفاتورة المرتبطة تنتمي للشركة الحالية إذا تم تغييرها
                if (isset($validatedData['invoice_id']) && $validatedData['invoice_id'] != $item->invoice_id) {
                    $invoice = \App\Models\Invoice::where('id', $validatedData['invoice_id'])
                        ->where('company_id', $companyId)
                        ->firstOrFail();
                }

                $item->update($validatedData);
                $item->load($this->relations);
                DB::commit();
                return api_success(new InvoiceItemResource($item), 'تم تحديث عنصر الفاتورة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث عنصر الفاتورة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث عنصر الفاتورة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف عنصر فاتورة محدد.
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

            $item = InvoiceItem::with(['company', 'creator'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoice_items.delete_all'), perm_key('admin.company')])) {
                $canDelete = $item->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_items.delete_children'))) {
                $canDelete = $item->belongsToCurrentCompany() && $item->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_items.delete_self'))) {
                $canDelete = $item->belongsToCurrentCompany() && $item->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف عنصر الفاتورة هذا.');
            }

            DB::beginTransaction();
            try {
                // حفظ نسخة من العنصر قبل حذفه لإرجاعها في الاستجابة
                $deletedItem = $item->replicate();
                $deletedItem->setRelations($item->getRelations());

                $item->delete();
                DB::commit();
                return api_success(new InvoiceItemResource($deletedItem), 'تم حذف عنصر الفاتورة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreStockRequest; // افتراض وجود طلب StoreStockRequest
use App\Http\Requests\Stock\UpdateStockRequest; // افتراض وجود طلب UpdateStockRequest
use App\Http\Resources\Stock\StockResource;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StockController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'creator',
            'company',
            'productVariant',
            'productVariant.product',
            'warehouse',
        ];
    }

    /**
     * عرض قائمة سجلات المخزون.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = Stock::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // فلترة الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع سجلات المخزون
            } elseif ($authUser->hasAnyPermission([perm_key('stocks.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('stocks.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('stocks.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض سجلات المخزون.');
            }

            // فلاتر الطلب
            if ($request->filled('product_variant_id')) {
                $query->where('product_variant_id', $request->input('product_variant_id'));
            }
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->input('warehouse_id'));
            }
            if ($request->filled('quantity_from')) {
                $query->where('quantity', '>=', $request->input('quantity_from'));
            }
            if ($request->filled('quantity_to')) {
                $query->where('quantity', '<=', $request->input('quantity_to'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
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

            $stocks = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            return api_success($stocks, 'تم جلب سجلات المخزون بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين سجل مخزون جديد.
     */
    public function store(StoreStockRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('stocks.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء سجلات مخزون.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                // التحقق من أن المتغير والمستودع ينتميان لنفس الشركة
                $productVariant = \App\Models\ProductVariant::where('id', $validatedData['product_variant_id'])
                    ->where('company_id', $companyId)
                    ->firstOrFail();
                $warehouse = \App\Models\Warehouse::where('id', $validatedData['warehouse_id'])
                    ->where('company_id', $companyId)
                    ->firstOrFail();

                $stock = Stock::create($validatedData);
                $stock->load($this->relations);
                DB::commit();
                return api_success(new StockResource($stock), 'تم إنشاء سجل المخزون بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين سجل المخزون.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ سجل المخزون.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض سجل مخزون محدد.
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

            $stock = Stock::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('stocks.view_all'), perm_key('admin.company')])) {
                $canView = $stock->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('stocks.view_children'))) {
                $canView = $stock->belongsToCurrentCompany() && $stock->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('stocks.view_self'))) {
                $canView = $stock->belongsToCurrentCompany() && $stock->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new StockResource($stock), 'تم استرداد سجل المخزون بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض سجل المخزون هذا.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث سجل مخزون محدد.
     */
    public function update(UpdateStockRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $stock = Stock::with(['company', 'creator', 'productVariant', 'warehouse'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('stocks.update_all'), perm_key('admin.company')])) {
                $canUpdate = $stock->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('stocks.update_children'))) {
                $canUpdate = $stock->belongsToCurrentCompany() && $stock->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('stocks.update_self'))) {
                $canUpdate = $stock->belongsToCurrentCompany() && $stock->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث سجل المخزون هذا.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                // التحقق من أن المتغير والمستودع ينتميان لنفس الشركة إذا تم تغييرها
                if (isset($validatedData['product_variant_id']) && $validatedData['product_variant_id'] != $stock->product_variant_id) {
                    \App\Models\ProductVariant::where('id', $validatedData['product_variant_id'])
                        ->where('company_id', $companyId)
                        ->firstOrFail();
                }
                if (isset($validatedData['warehouse_id']) && $validatedData['warehouse_id'] != $stock->warehouse_id) {
                    \App\Models\Warehouse::where('id', $validatedData['warehouse_id'])
                        ->where('company_id', $companyId)
                        ->firstOrFail();
                }

                $stock->update($validatedData);
                $stock->load($this->relations);
                DB::commit();
                return api_success(new StockResource($stock), 'تم تحديث سجل المخزون بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث سجل المخزون.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث سجل المخزون.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف سجل مخزون محدد.
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

            $stock = Stock::with(['company', 'creator'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('stocks.delete_all'), perm_key('admin.company')])) {
                $canDelete = $stock->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('stocks.delete_children'))) {
                $canDelete = $stock->belongsToCurrentCompany() && $stock->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('stocks.delete_self'))) {
                $canDelete = $stock->belongsToCurrentCompany() && $stock->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف سجل المخزون هذا.');
            }

            DB::beginTransaction();
            try {
                $deletedStock = $stock->replicate();
                $deletedStock->setRelations($stock->getRelations());

                $stock->delete();
                DB::commit();
                return api_success(new StockResource($deletedStock), 'تم حذف سجل المخزون بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف سجل المخزون.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

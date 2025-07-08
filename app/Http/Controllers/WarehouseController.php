<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Http\Resources\Warehouse\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // تم التأكد من وجود الاستيراد
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException; // تم إضافة هذا الاستيراد للتعامل مع أخطاء التحقق
use Throwable;

class WarehouseController extends Controller
{
    protected array $relations = [ // تم تغييرها إلى protected لتكون متسقة مع PlanController
        'company',
        'creator',
        'stocks',
    ];

    /**
     * عرض قائمة بالمستودعات.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $companyId = $authUser->company_id ?? null;

            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('المستخدم غير مرتبط بشركة ولا يملك صلاحيات المسؤول العام.');
            }

            $query = Warehouse::query()->with($this->relations);

            // فلترة الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع المستودعات
            } elseif ($authUser->hasAnyPermission([perm_key('warehouses.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض المستودعات.');
            }

            // فلاتر الطلب
            if ($request->filled('company_id')) {
                if ($request->input('company_id') != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return api_forbidden('ليس لديك إذن لعرض المستودعات لشركة أخرى.');
                }
                $query->where('company_id', $request->input('company_id'));
            }
            if ($request->filled('active')) {
                $query->where('active', (bool) $request->input('active'));
            }
            if ($request->filled('name')) { // إضافة فلتر الاسم
                $query->where('name', 'like', '%' . $request->input('name') . '%');
            }
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // التصفح والفرز
            $perPage = (int) $request->get('per_page', 20);
            $sortField = $request->input('sort_by', 'id'); // تغيير الحقل الافتراضي للفرز
            $sortOrder = $request->input('sort_order', 'desc');

            $warehouses = $query->orderBy($sortField, $sortOrder);
            $warehouses = $perPage == -1
                ? $warehouses->get()
                : $warehouses->paginate(max(1, $perPage));

            if ($warehouses->isEmpty()) {
                return api_success([], 'لم يتم العثور على مستودعات.');
            } else {
                return api_success(WarehouseResource::collection($warehouses), 'تم جلب المستودعات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين مستودع جديد.
     */
    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;


            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('warehouses.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء مستودعات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $warehouseCompanyId = $validatedData['company_id']
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء مستودع لهذه الشركة
                if ($warehouseCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء مستودعات لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }
                $validatedData['company_id'] = $warehouseCompanyId;

                $warehouse = Warehouse::create($validatedData);
                $warehouse->load($this->relations);
                DB::commit();
                Log::info('Warehouse created successfully.', ['warehouse_id' => $warehouse->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return api_success(new WarehouseResource($warehouse), 'تم إنشاء المستودع بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين المستودع.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Warehouse store failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return api_error('حدث خطأ أثناء حفظ المستودع.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض مستودع محدد.
     */
    public function show(Warehouse $warehouse): JsonResponse // استخدام Route Model Binding
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $warehouse->load($this->relations);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('warehouses.view_all'), perm_key('admin.company')])) {
                $canView = $warehouse->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.view_children'))) {
                $canView = $warehouse->belongsToCurrentCompany() && $warehouse->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.view_self'))) {
                $canView = $warehouse->belongsToCurrentCompany() && $warehouse->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new WarehouseResource($warehouse), 'تم استرداد المستودع بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذا المستودع.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث مستودع محدد.
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse // استخدام Route Model Binding
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            $warehouse->load(['company', 'creator']); // تحميل العلاقات للتحقق من الصلاحيات

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('warehouses.update_all'), perm_key('admin.company')])) {
                $canUpdate = $warehouse->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.update_children'))) {
                $canUpdate = $warehouse->belongsToCurrentCompany() && $warehouse->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.update_self'))) {
                $canUpdate = $warehouse->belongsToCurrentCompany() && $warehouse->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذا المستودع.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // التأكد من أن المستخدم مصرح له بتغيير company_id إذا كان سوبر أدمن
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $warehouse->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة المستودع إلا إذا كنت مدير عام.');
                }
                // إذا لم يتم تحديد company_id في الطلب ولكن المستخدم سوبر أدمن، لا تغير company_id الخاصة بالمستودع الحالي
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }
                $warehouse->update($validatedData);
                $warehouse->load($this->relations);
                DB::commit();
                Log::info('Warehouse updated successfully.', ['warehouse_id' => $warehouse->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return api_success(new WarehouseResource($warehouse), 'تم تحديث المستودع بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث المستودع.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();

                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف مستودع محدد.
     */
    public function destroy(Warehouse $warehouse): JsonResponse // استخدام Route Model Binding
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $warehouse->load(['company', 'creator', 'stocks']); // تحميل العلاقات للتحقق من الصلاحيات

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('warehouses.delete_all'), perm_key('admin.company')])) {
                $canDelete = $warehouse->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.delete_children'))) {
                $canDelete = $warehouse->belongsToCurrentCompany() && $warehouse->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.delete_self'))) {
                $canDelete = $warehouse->belongsToCurrentCompany() && $warehouse->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذا المستودع.');
            }

            DB::beginTransaction();
            try {
                // التحقق مما إذا كان المستودع مرتبطًا بأي stocks قبل الحذف
                if ($warehouse->stocks()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف المستودع. إنه يحتوي على سجلات مخزون مرتبطة.', [], 409);
                }

                $deletedWarehouse = $warehouse->replicate(); // نسخ المستودع قبل الحذف لإرجاعه
                $deletedWarehouse->setRelations($warehouse->getRelations()); // نسخ العلاقات أيضًا

                $warehouse->delete();
                DB::commit();
                Log::info('Warehouse deleted successfully.', ['warehouse_id' => $deletedWarehouse->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return api_success(new WarehouseResource($deletedWarehouse), 'تم حذف المستودع بنجاح.'); // إرجاع المورد المحذوف
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Warehouse deletion failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'warehouse_id' => $warehouse->id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return api_error('حدث خطأ أثناء حذف المستودع.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

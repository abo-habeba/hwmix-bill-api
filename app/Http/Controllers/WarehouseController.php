<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Http\Resources\Warehouse\WarehouseResource;
use App\Models\Warehouse; // تم تصحيح الخطأ هنا
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // تم إضافة هذا الاستيراد
use Illuminate\Support\Facades\DB; // تم إضافة هذا الاستيراد
use Illuminate\Support\Facades\Log; // تم إضافة هذا الاستيراد
use Throwable; // تم إضافة هذا الاستيراد
// use Symfony\Component\HttpFoundation\Response; // تم التعليق عليه لعدم استخدامه بشكل مباشر

// دالة مساعدة لضمان الاتساق في مفاتيح الأذونات (إذا لم تكن معرفة عالميا)
// if (!function_exists('perm_key')) {
//     function perm_key(string $permission): string
//     {
//         return $permission;
//     }
// }

class WarehouseController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
            'stocks',
        ];
    }

    /**
     * عرض قائمة بالمستودعات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();
            $query = Warehouse::with($this->relations);
            $companyId = $authUser->company_id; // معرف الشركة النشطة للمستخدم

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع المستودعات (لا توجد قيود إضافية على الاستعلام)
            } elseif ($authUser->hasAnyPermission([perm_key('warehouses.view_all'), perm_key('admin.company')])) {
                // يرى جميع المستودعات الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.view_children'))) {
                // يرى المستودعات التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.view_self'))) {
                // يرى المستودعات التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view warehouses.'], 403);
            }

            // تطبيق فلاتر البحث
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('name', 'like', "%$search%")
                        ->orWhere('address', 'like', "%$search%");
                });
            }
            if ($request->filled('active')) {
                $query->where('active', (bool) $request->input('active'));
            }

            // الفرز والتصفح
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->input('per_page', 10);
            $warehouses = $query->paginate($perPage);

            return WarehouseResource::collection($warehouses)->additional([
                'total' => $warehouses->total(),
                'current_page' => $warehouses->currentPage(),
                'last_page' => $warehouses->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Warehouse index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error retrieving warehouses.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreWarehouseRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreWarehouseRequest $request)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // صلاحيات إنشاء مستودع
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('warehouses.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to create warehouses.'
                ], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $validatedData['company_id'] = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء مستودع لهذه الشركة
                if ($validatedData['company_id'] != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You can only create warehouses for your current company unless you are a Super Admin.'], 403);
                }

                $validatedData['created_by'] = $authUser->id;
                $validatedData['active'] = (bool) ($validatedData['active'] ?? true); // افتراضي نشط عند الإنشاء

                $warehouse = Warehouse::create($validatedData);
                $warehouse->load($this->relations);
                DB::commit();
                Log::info('Warehouse created successfully.', ['warehouse_id' => $warehouse->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new WarehouseResource($warehouse);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Warehouse store failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'error' => 'Error saving warehouse.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Warehouse store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error saving warehouse.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Warehouse\WarehouseResource
     */
    public function show(string $id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            $warehouse = Warehouse::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي مستودع
            } elseif ($authUser->hasAnyPermission([perm_key('warehouses.view_all'), perm_key('admin.company')])) {
                // يرى إذا كان المستودع ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $warehouse->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.view_children'))) {
                // يرى إذا كان المستودع أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $warehouse->belongsToCurrentCompany() && $warehouse->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.view_self'))) {
                // يرى إذا كان المستودع أنشأه هو وتابع للشركة النشطة
                $canView = $warehouse->belongsToCurrentCompany() && $warehouse->createdByCurrentUser();
            } else {
                // لا توجد صلاحية عرض
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this warehouse.'], 403);
            }

            if ($canView) {
                return new WarehouseResource($warehouse);
            }

            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this warehouse.'], 403);
        } catch (Throwable $e) {
            Log::error('Warehouse show failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'warehouse_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error retrieving warehouse.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateWarehouseRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Warehouse\WarehouseResource
     */
    public function update(UpdateWarehouseRequest $request, string $id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $warehouse = Warehouse::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي مستودع
            } elseif ($authUser->hasAnyPermission([perm_key('warehouses.update_all'), perm_key('admin.company')])) {
                // يمكنه تعديل أي مستودع داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $warehouse->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.update_children'))) {
                // يمكنه تعديل المستودعات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $warehouse->belongsToCurrentCompany() && $warehouse->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.update_self'))) {
                // يمكنه تعديل مستودعه الخاص الذي أنشأه وتابع للشركة النشطة
                $canUpdate = $warehouse->belongsToCurrentCompany() && $warehouse->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this warehouse.'], 403);
            }

            if (!$canUpdate) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this warehouse.'], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للمستودع.
                $validatedData['company_id'] = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $warehouse->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل مستودع لهذه الشركة
                if ($validatedData['company_id'] != $warehouse->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You cannot change a warehouse\'s company unless you are a Super Admin.'], 403);
                }

                $validatedData['active'] = (bool) ($validatedData['active'] ?? $warehouse->active);

                $warehouse->update($validatedData);
                $warehouse->load($this->relations);
                DB::commit();
                Log::info('Warehouse updated successfully.', ['warehouse_id' => $warehouse->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new WarehouseResource($warehouse);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Warehouse update failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'warehouse_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'error' => 'Error updating warehouse.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Warehouse update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'warehouse_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error updating warehouse.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $warehouse = Warehouse::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true; // المسؤول العام يمكنه حذف أي مستودع
            } elseif ($authUser->hasAnyPermission([perm_key('warehouses.delete_all'), perm_key('admin.company')])) {
                // يمكنه حذف أي مستودع داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $warehouse->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.delete_children'))) {
                // يمكنه حذف المستودعات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $warehouse->belongsToCurrentCompany() && $warehouse->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('warehouses.delete_self'))) {
                // يمكنه حذف مستودعه الخاص الذي أنشأه وتابع للشركة النشطة
                $canDelete = $warehouse->belongsToCurrentCompany() && $warehouse->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this warehouse.'], 403);
            }

            if (!$canDelete) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this warehouse.'], 403);
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كان المستودع مرتبطًا بأي stocks قبل الحذف
                // هذا يمنع حذف المستودع الذي يحتوي على مخزون نشط
                if ($warehouse->stocks()->exists()) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Conflict',
                        'message' => 'Cannot delete warehouse. It contains associated stock records.',
                    ], 409);
                }

                $warehouse->delete();
                DB::commit();
                Log::info('Warehouse deleted successfully.', ['warehouse_id' => $id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(['message' => 'Warehouse deleted successfully'], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Warehouse deletion failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'warehouse_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'error' => 'Error deleting warehouse.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Warehouse deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'warehouse_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error deleting warehouse.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}

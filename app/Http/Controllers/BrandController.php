<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\Brand\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // تم تصحيح الخطأ هنا
use Illuminate\Support\Facades\DB; // تم تصحيح الخطأ هنا
use Illuminate\Support\Facades\Log; // تم تصحيح الخطأ هنا
use Illuminate\Validation\ValidationException; // تم تصحيح الخطأ هنا
use Throwable; // تم إضافة هذا الاستيراد



/**
 * Class BrandController
 *
 * تحكم في عمليات العلامات التجارية (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class BrandController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'creator',
            'company',   // للتحقق من belongsToCurrentCompany
            'products',  // للتحقق من المنتجات المرتبطة قبل الحذف
        ];
    }

    /**
     * عرض جميع العلامات التجارية.
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = Brand::with($this->relations);
            $companyId = $authUser->company_id;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // تطبيق منطق الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الماركات (لا قيود إضافية)
            } elseif ($authUser->hasAnyPermission([perm_key('brands.view_all'), perm_key('admin.company')])) {
                // يرى جميع الماركات الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.view_children'))) {
                // يرى الماركات التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.view_self'))) {
                // يرى الماركات التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view brands.'], 403);
            }

            // تطبيق فلاتر البحث
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('name', 'like', "%$search%")
                        ->orWhere('desc', 'like', "%$search%");
                });
            }
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->input('name') . '%');
            }

            // الفرز والتصفح
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = max(1, (int) $request->get('per_page', 10));
            $brands = $query->paginate($perPage);

            return BrandResource::collection($brands)->additional([
                'total' => $brands->total(),
                'current_page' => $brands->currentPage(),
                'last_page' => $brands->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Brand index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving brands.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * إضافة علامة تجارية جديدة.
     *
     * @param StoreBrandRequest $request
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Brand\BrandResource
     */
    public function store(StoreBrandRequest $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // صلاحيات إنشاء ماركة
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('brands.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to create brands.'
                ], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $brandCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء ماركة لهذه الشركة
                if ($brandCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You can only create brands for your current company unless you are a Super Admin.'], 403);
                }

                $validatedData['company_id'] = $brandCompanyId;
                $validatedData['created_by'] = $authUser->id;

                $brand = Brand::create($validatedData);
                $brand->load($this->relations);
                DB::commit();
                Log::info('Brand created successfully.', ['brand_id' => $brand->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new BrandResource($brand);
            } catch (ValidationException $e) {
                DB::rollBack();
                Log::error('Brand store validation failed: ' . $e->getMessage(), [
                    'errors' => $e->errors(),
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Brand store failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error saving brand.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Brand store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error saving brand.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * عرض علامة تجارية محددة.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Brand\BrandResource
     */
    public function show(string $id)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            $brand = Brand::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي ماركة
            } elseif ($authUser->hasAnyPermission([perm_key('brands.view_all'), perm_key('admin.company')])) {
                // يرى إذا كانت الماركة تنتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $brand->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.view_children'))) {
                // يرى إذا كانت الماركة أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canView = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.view_self'))) {
                // يرى إذا كانت الماركة أنشأها هو وتابعة للشركة النشطة
                $canView = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this brand.'], 403);
            }

            if ($canView) {
                return new BrandResource($brand);
            }

            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this brand.'], 403);
        } catch (Throwable $e) {
            Log::error('Brand show failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'brand_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving brand.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * تحديث علامة تجارية.
     *
     * @param UpdateBrandRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Brand\BrandResource
     */
    public function update(UpdateBrandRequest $request, string $id)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $brand = Brand::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي ماركة
            } elseif ($authUser->hasAnyPermission([perm_key('brands.update_any'), perm_key('admin.company')])) {
                // يمكنه تعديل أي ماركة داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $brand->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.update_children'))) {
                // يمكنه تعديل الماركات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.update_self'))) {
                // يمكنه تعديل ماركته الخاصة التي أنشأها وتابعة للشركة النشطة
                $canUpdate = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this brand.'], 403);
            }

            if (!$canUpdate) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this brand.'], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $updatedBy = $authUser->id;

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للماركة.
                $brandCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $brand->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل ماركة لشركة أخرى (فقط سوبر أدمن)
                if ($brandCompanyId != $brand->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You cannot change a brand\'s company unless you are a Super Admin.'], 403);
                }

                $validatedData['company_id'] = $brandCompanyId; // تحديث company_id في البيانات المصدقة
                $validatedData['updated_by'] = $updatedBy; // من قام بالتعديل

                $brand->update($validatedData);
                $brand->load($this->relations);
                DB::commit();
                Log::info('Brand updated successfully.', ['brand_id' => $brand->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new BrandResource($brand);
            } catch (ValidationException $e) {
                DB::rollBack();
                Log::error('Brand update validation failed: ' . $e->getMessage(), [
                    'errors' => $e->errors(),
                    'user_id' => Auth::id(),
                    'brand_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Brand update failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'brand_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error updating brand.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Brand update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'brand_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error updating brand.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * حذف علامة تجارية.
     *
     * @param Brand $brand
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Brand $brand)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $brand->load(['company', 'creator']); // تم تحميلها هنا لأنها تمر كنموذج Model Binding

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true; // المسؤول العام يمكنه حذف أي ماركة
            } elseif ($authUser->hasAnyPermission([perm_key('brands.delete_any'), perm_key('admin.company')])) {
                // يمكنه حذف أي ماركة داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $brand->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.delete_children'))) {
                // يمكنه حذف الماركات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.delete_self'))) {
                // يمكنه حذف ماركته الخاصة التي أنشأها وتابعة للشركة النشطة
                $canDelete = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this brand.'], 403);
            }

            if (!$canDelete) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this brand.'], 403);
            }

            DB::beginTransaction();
            try {
                // تحقق من وجود منتجات مرتبطة
                if ($brand->products()->exists()) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Conflict',
                        'message' => 'Cannot delete brand. It is associated with one or more products.',
                    ], 409);
                }

                $brand->delete();
                DB::commit();
                Log::info('Brand deleted successfully.', ['brand_id' => $brand->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(['message' => 'Brand deleted successfully'], 200); // 200 OK for successful deletion
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Brand deletion failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'brand_id' => $brand->id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error deleting brand.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Brand deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'brand_id' => $brand->id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error deleting brand.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}

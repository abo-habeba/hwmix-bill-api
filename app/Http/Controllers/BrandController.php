<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\Brand\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // للتأكد من استيراد JsonResponse
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

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
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = Brand::with($this->relations);
            $companyId = $authUser->company_id ?? null;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
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
                return api_forbidden('ليس لديك إذن لعرض العلامات التجارية.');
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

            return api_success($brands, 'تم استرداد العلامات التجارية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * إضافة علامة تجارية جديدة.
     *
     * @param StoreBrandRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreBrandRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء ماركة
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('brands.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء علامات تجارية.');
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
                    return api_forbidden('يمكنك فقط إنشاء علامات تجارية لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }

                $validatedData['company_id'] = $brandCompanyId;
                $validatedData['created_by'] = $authUser->id;

                $brand = Brand::create($validatedData);
                $brand->load($this->relations);
                DB::commit();
                return api_success(new BrandResource($brand), 'تم إنشاء العلامة التجارية بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين العلامة التجارية.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ العلامة التجارية.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * عرض علامة تجارية محددة.
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

            $brand = Brand::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('brands.view_all'), perm_key('admin.company')])) {
                $canView = $brand->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.view_children'))) {
                $canView = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.view_self'))) {
                $canView = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new BrandResource($brand), 'تم استرداد العلامة التجارية بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه العلامة التجارية.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تحديث علامة تجارية.
     *
     * @param UpdateBrandRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateBrandRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $brand = Brand::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('brands.update_all'), perm_key('admin.company')])) {
                $canUpdate = $brand->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.update_children'))) {
                $canUpdate = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.update_self'))) {
                $canUpdate = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذه العلامة التجارية.');
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
                    return api_forbidden('لا يمكنك تغيير شركة العلامة التجارية ما لم تكن مسؤولاً عامًا.');
                }

                $validatedData['company_id'] = $brandCompanyId; // تحديث company_id في البيانات المصدقة
                $validatedData['updated_by'] = $updatedBy; // من قام بالتعديل

                $brand->update($validatedData);
                $brand->load($this->relations);
                DB::commit();
                return api_success(new BrandResource($brand), 'تم تحديث العلامة التجارية بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث العلامة التجارية.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث العلامة التجارية.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * حذف علامة تجارية.
     *
     * @param Brand $brand
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Brand $brand): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $brand->load(['company', 'creator']); // تم تحميلها هنا لأنها تمر كنموذج Model Binding

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('brands.delete_all'), perm_key('admin.company')])) {
                $canDelete = $brand->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.delete_children'))) {
                $canDelete = $brand->belongsToCurrentCompany() && $brand->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('brands.delete_self'))) {
                $canDelete = $brand->belongsToCurrentCompany() && $brand->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذه العلامة التجارية.');
            }

            DB::beginTransaction();
            try {
                // تحقق من وجود منتجات مرتبطة
                if ($brand->products()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف العلامة التجارية. إنها مرتبطة بمنتج واحد أو أكثر.', [], 409);
                }

                // حفظ نسخة من العلامة التجارية قبل حذفها لإرجاعها في الاستجابة
                $deletedBrand = $brand->replicate();
                $deletedBrand->setRelations($brand->getRelations()); // نسخ العلاقات المحملة

                $brand->delete();
                DB::commit();
                return api_success(new BrandResource($deletedBrand), 'تم حذف العلامة التجارية بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف العلامة التجارية.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\Category\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CategoryController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'children.children', // لتحميل الفئات الفرعية المتداخلة
            'parent',            // الفئة الأم
            'company',           // للتحقق من belongsToCurrentCompany
            'creator',           // للتحقق من createdByCurrentUser/OrChildren
            'products',          // للتحقق من المنتجات المرتبطة قبل الحذف
        ];
    }

    /**
     * عرض قائمة الموارد.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = Category::with($this->relations)->whereNull('parent_id');
            $companyId = $authUser->company_id ?? null;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // تطبيق منطق الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الفئات (لا قيود إضافية)
            } elseif ($authUser->hasAnyPermission([perm_key('categories.view_all'), perm_key('admin.company')])) {
                // يرى جميع الفئات الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.view_children'))) {
                // يرى الفئات التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.view_self'))) {
                // يرى الفئات التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض الفئات.');
            }

            // التحقق من وجود قيمة البحث
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('name', 'LIKE', "%$search%");
            }

            // الفرز والتصفح
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = max(1, (int) $request->get('per_page', 10));
            $categories = $query->get(); // استخدام get بدلاً من paginate للحصول على جميع الفئات

            if ($categories->isEmpty()) {
                return api_success($categories, 'لم يتم العثور على فئات.');
            } else {
                return api_success(CategoryResource::collection($categories), 'تم استرداد الفئات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreCategoryRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء فئة
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('categories.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء فئات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $categoryCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء فئة لهذه الشركة
                if ($categoryCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء فئات لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }

                $validatedData['company_id'] = $categoryCompanyId;
                $validatedData['created_by'] = $authUser->id;

                $category = Category::create($validatedData);
                $category->load($this->relations); // تحميل العلاقات بعد الإنشاء
                DB::commit();
                return api_success(new CategoryResource($category), 'تم إنشاء الفئة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين الفئة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ الفئة.', [], 500);
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

            $category = Category::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('categories.view_all'), perm_key('admin.company')])) {
                $canView = $category->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.view_children'))) {
                $canView = $category->belongsToCurrentCompany() && $category->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.view_self'))) {
                $canView = $category->belongsToCurrentCompany() && $category->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new CategoryResource($category), 'تم استرداد الفئة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه الفئة.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateCategoryRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $category = Category::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('categories.update_all'), perm_key('admin.company')])) {
                $canUpdate = $category->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.update_children'))) {
                $canUpdate = $category->belongsToCurrentCompany() && $category->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.update_self'))) {
                $canUpdate = $category->belongsToCurrentCompany() && $category->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذه الفئة.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $updatedBy = $authUser->id;

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للفئة.
                $categoryCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $category->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل فئة لشركة أخرى (فقط سوبر أدمن)
                if ($categoryCompanyId != $category->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة الفئة ما لم تكن مسؤولاً عامًا.');
                }

                $validatedData['company_id'] = $categoryCompanyId;
                $validatedData['updated_by'] = $updatedBy;

                $category->update($validatedData);
                $category->load($this->relations); // تحميل العلاقات بعد التحديث
                DB::commit();
                return api_success(new CategoryResource($category), 'تم تحديث الفئة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث الفئة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الفئة.', [], 500);
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

            $category = Category::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('categories.delete_all'), perm_key('admin.company')])) {
                $canDelete = $category->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.delete_children'))) {
                $canDelete = $category->belongsToCurrentCompany() && $category->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('categories.delete_self'))) {
                $canDelete = $category->belongsToCurrentCompany() && $category->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذه الفئة.');
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كانت الفئة مرتبطة بأي منتجات أو فئات فرعية
                if ($category->products()->exists()) { // افتراض أن لديك علاقة products في نموذج Category
                    DB::rollBack();
                    return api_error('لا يمكن حذف الفئة. إنها مرتبطة بمنتج واحد أو أكثر.', [], 409);
                }
                if ($category->children()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف الفئة. إنها تحتوي على فئات فرعية.', [], 409);
                }

                // حفظ نسخة من الفئة قبل حذفها لإرجاعها في الاستجابة
                $deletedCategory = $category->replicate();
                $deletedCategory->setRelations($category->getRelations()); // نسخ العلاقات المحملة

                $category->delete();
                DB::commit();
                return api_success(new CategoryResource($deletedCategory), 'تم حذف الفئة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف الفئة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttributeValue\StoreAttributeValueRequest;
use App\Http\Requests\AttributeValue\UpdateAttributeValueRequest;
use App\Http\Resources\AttributeValue\AttributeValueResource;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // للتأكد من استيراد JsonResponse
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;


/**
 * Class AttributeValueController
 *
 * تحكم في عمليات قيم السمات (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class AttributeValueController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'attribute', // السمة الأم
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
        ];
    }

    /**
     * عرض جميع قيم السمات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = AttributeValue::with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة أو لديك صلاحية مدير عام.');
            }

            // تطبيق منطق الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع قيم السمات (لا قيود إضافية)
            } elseif ($authUser->hasAnyPermission([perm_key('attribute_values.view_all'), perm_key('admin.company')])) {
                // يرى جميع قيم السمات الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.view_children'))) {
                // يرى قيم السمات التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.view_self'))) {
                // يرى قيم السمات التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض قيم السمات.');
            }

            // تطبيق فلاتر البحث
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('name', 'like', "%$search%")
                        ->orWhere('value', 'like', "%$search%")
                        ->orWhereHas('attribute', function ($aq) use ($search) {
                            $aq->where('name', 'like', "%$search%");
                        });
                });
            }
            if ($request->filled('attribute_id')) {
                $query->where('attribute_id', $request->input('attribute_id'));
            }
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->input('name') . '%');
            }

            // الفرز والتصفح
            $perPage = max(1, (int) $request->get('per_page', 10));
            $sortField = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $attributeValues = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($attributeValues->isEmpty()) {
                return api_success([], 'لم يتم العثور على قيم سمات.');
            } else {
                return api_success(AttributeValueResource::collection($attributeValues), 'تم جلب قيم السمات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * إضافة قيمة سمة جديدة.
     *
     * @param StoreAttributeValueRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreAttributeValueRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يجب تسجيل الدخول ووجود شركة نشطة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('attribute_values.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك صلاحية لإنشاء قيم السمات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $attribute = \App\Models\Attribute::with('company')->find($validatedData['attribute_id']);
                if (!$attribute || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $attribute->company_id !== $companyId)) {
                    DB::rollBack();
                    return api_forbidden('السمة الأم غير موجودة أو غير تابعة لشركتك.');
                }
                $valueCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $attribute->company_id;
                if ($valueCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء قيم سمات لشركتك النشطة.');
                }
                $validatedData['company_id'] = $valueCompanyId;
                $validatedData['created_by'] = $authUser->id;
                $attributeValue = AttributeValue::create($validatedData);
                $attributeValue->load($this->relations);
                DB::commit();
                return api_success(new AttributeValueResource($attributeValue), 'تم إنشاء قيمة السمة بنجاح');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error($e->getMessage(), $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ قيمة السمة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض قيمة سمة محددة.
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
                return api_unauthorized('يجب تسجيل الدخول ووجود شركة نشطة.');
            }

            $attributeValue = AttributeValue::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('attribute_values.view_all'), perm_key('admin.company')])) {
                $canView = $attributeValue->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.view_children'))) {
                $canView = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.view_self'))) {
                $canView = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new AttributeValueResource($attributeValue), 'تم جلب قيمة السمة بنجاح');
            }

            return api_forbidden('ليس لديك صلاحية لعرض هذه القيمة.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث قيمة سمة.
     *
     * @param UpdateAttributeValueRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateAttributeValueRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يجب تسجيل الدخول ووجود شركة نشطة.');
            }

            $attributeValue = AttributeValue::with(['company', 'creator', 'attribute'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('attribute_values.update_all'), perm_key('admin.company')])) {
                $canUpdate = $attributeValue->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.update_children'))) {
                $canUpdate = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.update_self'))) {
                $canUpdate = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك صلاحية لتحديث هذه القيمة.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $updatedBy = $authUser->id;
                if (isset($validatedData['attribute_id']) && $validatedData['attribute_id'] != $attributeValue->attribute_id) {
                    $newAttribute = \App\Models\Attribute::with('company')->find($validatedData['attribute_id']);
                    if (!$newAttribute || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $newAttribute->company_id !== $companyId)) {
                        DB::rollBack();
                        return api_forbidden('السمة الأم الجديدة غير موجودة أو غير تابعة لشركتك.');
                    }
                }
                $valueCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $attributeValue->company_id;
                if ($valueCompanyId != $attributeValue->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة قيمة السمة إلا إذا كنت مدير عام.');
                }
                $validatedData['company_id'] = $valueCompanyId;
                $validatedData['updated_by'] = $updatedBy;
                $attributeValue->update($validatedData);
                $attributeValue->load($this->relations);
                DB::commit();
                return api_success(new AttributeValueResource($attributeValue), 'تم تحديث قيمة السمة بنجاح');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error($e->getMessage(), $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث قيمة السمة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف قيمة سمة.
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
                return api_unauthorized('يجب تسجيل الدخول ووجود شركة نشطة.');
            }

            $attributeValue = AttributeValue::with(['company', 'creator'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('attribute_values.delete_all'), perm_key('admin.company')])) {
                $canDelete = $attributeValue->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.delete_children'))) {
                $canDelete = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.delete_self'))) {
                $canDelete = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك صلاحية لحذف هذه القيمة.');
            }

            DB::beginTransaction();
            try {
                if (\App\Models\ProductVariantAttribute::where('attribute_value_id', $attributeValue->id)->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف قيمة السمة لأنها مرتبطة بمتغيرات منتجات.', [], 409);
                }

                // حفظ نسخة من العنصر قبل حذفه لإرجاعه في الاستجابة
                $deletedAttributeValue = $attributeValue->replicate();
                $deletedAttributeValue->setRelations($attributeValue->getRelations()); // نسخ العلاقات المحملة

                $attributeValue->delete();
                DB::commit();
                return api_success(new AttributeValueResource($deletedAttributeValue), 'تم حذف قيمة السمة بنجاح');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف قيمة السمة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

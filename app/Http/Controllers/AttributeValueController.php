<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttributeValue\StoreAttributeValueRequest;
use App\Http\Requests\AttributeValue\UpdateAttributeValueRequest;
use App\Http\Resources\AttributeValue\AttributeValueResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariantAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable; // تأكد من استيراد Throwable

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
            'attribute',
            'creator',
        ];
    }

    /**
     * عرض جميع قيم السمات.
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AttributeValue::with($this->relations);

            // تطبيق فلاتر البحث
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
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
            // ✅ تمرير رسالة الاستثناء الأصلي وتفاصيل التتبع
            return api_exception($e);
        }
    }

    /**
     * إضافة قيمة سمة جديدة.
     * @param StoreAttributeValueRequest $request
     * @return JsonResponse
     */
    public function store(StoreAttributeValueRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                $attribute = Attribute::find($validatedData['attribute_id']);
                if (!$attribute) {
                    DB::rollBack();
                    return api_error('السمة الأم غير موجودة.', [], 404);
                }

                // التأكد أن المستخدم صاحب الـ ID=1 موجود في جدول users
                // هذا هو السبب الأكثر ترجيحاً للخطأ الحالي
                $validatedData['created_by'] = 1;

                $attributeValue = AttributeValue::create($validatedData);
                $attributeValue->load($this->relations);
                DB::commit();
                return api_success(new AttributeValueResource($attributeValue), 'تم إنشاء قيمة السمة بنجاح');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error($e->getMessage(), $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                // ✅ تمرير رسالة الاستثناء الأصلية لتحديد المشكلة
                return api_error($e->getMessage(), [], 500);
            }
        } catch (Throwable $e) {
            // ✅ تمرير رسالة الاستثناء الأصلي وتفاصيل التتبع
            return api_exception($e);
        }
    }

    /**
     * عرض قيمة سمة محددة.
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $attributeValue = AttributeValue::with($this->relations)->findOrFail($id);
            return api_success(new AttributeValueResource($attributeValue), 'تم جلب قيمة السمة بنجاح');
        } catch (Throwable $e) {
            // ✅ تمرير رسالة الاستثناء الأصلي وتفاصيل التتبع
            return api_exception($e);
        }
    }

    /**
     * تحديث قيمة سمة.
     * @param UpdateAttributeValueRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(UpdateAttributeValueRequest $request, string $id): JsonResponse
    {
        try {
            $attributeValue = AttributeValue::with(['company', 'creator', 'attribute'])->findOrFail($id);

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                if (isset($validatedData['attribute_id']) && $validatedData['attribute_id'] != $attributeValue->attribute_id) {
                    $newAttribute = Attribute::find($validatedData['attribute_id']);
                    if (!$newAttribute) {
                        DB::rollBack();
                        return api_error('السمة الأم الجديدة غير موجودة.', [], 404);
                    }
                }

                // التأكد أن المستخدم صاحب الـ ID=1 موجود في جدول users
                // هذا هو السبب الأكثر ترجيحاً للخطأ الحالي
                $validatedData['updated_by'] = 1;

                $attributeValue->update($validatedData);
                $attributeValue->load($this->relations);
                DB::commit();
                return api_success(new AttributeValueResource($attributeValue), 'تم تحديث قيمة السمة بنجاح');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error($e->getMessage(), $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                // ✅ تمرير رسالة الاستثناء الأصلية لتحديد المشكلة
                return api_error($e->getMessage(), [], 500);
            }
        } catch (Throwable $e) {
            // ✅ تمرير رسالة الاستثناء الأصلي وتفاصيل التتبع
            return api_exception($e);
        }
    }

    /**
     * حذف قيمة سمة.
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $attributeValue = AttributeValue::with(['company', 'creator'])->findOrFail($id);

            DB::beginTransaction();
            try {
                if (ProductVariantAttribute::where('attribute_value_id', $attributeValue->id)->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف قيمة السمة لأنها مرتبطة بمتغيرات منتجات.', [], 409);
                }

                $deletedAttributeValue = $attributeValue->replicate();
                $deletedAttributeValue->setRelations($attributeValue->getRelations());

                $attributeValue->delete();
                DB::commit();
                return api_success(new AttributeValueResource($deletedAttributeValue), 'تم حذف قيمة السمة بنجاح');
            } catch (Throwable $e) {
                DB::rollBack();
                // ✅ تمرير رسالة الاستثناء الأصلية لتحديد المشكلة
                return api_error($e->getMessage(), [], 500);
            }
        } catch (Throwable $e) {
            // ✅ تمرير رسالة الاستثناء الأصلي وتفاصيل التتبع
            return api_exception($e);
        }
    }
}

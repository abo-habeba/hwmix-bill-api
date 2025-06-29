<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttributeValue\StoreAttributeValueRequest;
use App\Http\Requests\AttributeValue\UpdateAttributeValueRequest;
use App\Http\Resources\AttributeValue\AttributeValueResource;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

// دالة مساعدة لضمان الاتساق في مفاتيح الأذونات (إذا لم تكن معرفة عالميا)
// if (!function_exists('perm_key')) {
//     function perm_key(string $permission): string
//     {
//         return $permission;
//     }
// }

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
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = AttributeValue::with($this->relations);
            $companyId = $authUser->company_id;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
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
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view attribute values.'], 403);
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
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = max(1, (int) $request->get('per_page', 10));
            $attributeValues = $query->paginate($perPage);

            return AttributeValueResource::collection($attributeValues)->additional([
                'total' => $attributeValues->total(),
                'current_page' => $attributeValues->currentPage(),
                'last_page' => $attributeValues->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('AttributeValue index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving attribute values.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * إضافة قيمة سمة جديدة.
     *
     * @param StoreAttributeValueRequest $request
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\AttributeValue\AttributeValueResource
     */
    public function store(StoreAttributeValueRequest $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // صلاحيات إنشاء قيمة سمة
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('attribute_values.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to create attribute values.'
                ], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // التحقق من أن السمة الأم موجودة وتابعة لشركة المستخدم أو أن المستخدم super_admin
                $attribute = \App\Models\Attribute::with('company')->find($validatedData['attribute_id']);
                if (!$attribute || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $attribute->company_id !== $companyId)) {
                    DB::rollBack();
                    return response()->json(['error' => 'Forbidden', 'message' => 'Parent attribute not found or not accessible within your company.'], 403);
                }

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للسمة الأم.
                $valueCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $attribute->company_id;

                // التأكد من أن المستخدم مصرح له بإنشاء قيمة سمة لهذه الشركة
                if ($valueCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You can only create attribute values for your current company unless you are a Super Admin.'], 403);
                }

                $validatedData['company_id'] = $valueCompanyId;
                $validatedData['created_by'] = $authUser->id;

                $attributeValue = AttributeValue::create($validatedData);
                $attributeValue->load($this->relations);
                DB::commit();
                Log::info('Attribute value created successfully.', ['attribute_value_id' => $attributeValue->id, 'attribute_id' => $attribute->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new AttributeValueResource($attributeValue);
            } catch (ValidationException $e) {
                DB::rollBack();
                Log::error('AttributeValue store validation failed: ' . $e->getMessage(), [
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
                Log::error('AttributeValue store failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error saving attribute value.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('AttributeValue store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error saving attribute value.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * عرض قيمة سمة محددة.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\AttributeValue\AttributeValueResource
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

            $attributeValue = AttributeValue::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي قيمة سمة
            } elseif ($authUser->hasAnyPermission([perm_key('attribute_values.view_all'), perm_key('admin.company')])) {
                // يرى إذا كانت قيمة السمة تنتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $attributeValue->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.view_children'))) {
                // يرى إذا كانت قيمة السمة أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canView = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.view_self'))) {
                // يرى إذا كانت قيمة السمة أنشأها هو وتابعة للشركة النشطة
                $canView = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this attribute value.'], 403);
            }

            if ($canView) {
                return new AttributeValueResource($attributeValue);
            }

            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this attribute value.'], 403);
        } catch (Throwable $e) {
            Log::error('AttributeValue show failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'attribute_value_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving attribute value.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * تحديث قيمة سمة.
     *
     * @param UpdateAttributeValueRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\AttributeValue\AttributeValueResource
     */
    public function update(UpdateAttributeValueRequest $request, string $id)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ والسمة الأم)
            $attributeValue = AttributeValue::with(['company', 'creator', 'attribute'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي قيمة سمة
            } elseif ($authUser->hasAnyPermission([perm_key('attribute_values.update_any'), perm_key('admin.company')])) {
                // يمكنه تعديل أي قيمة سمة داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $attributeValue->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.update_children'))) {
                // يمكنه تعديل قيم السمات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.update_self'))) {
                // يمكنه تعديل قيمة سمته الخاصة التي أنشأها وتابعة للشركة النشطة
                $canUpdate = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this attribute value.'], 403);
            }

            if (!$canUpdate) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this attribute value.'], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $updatedBy = $authUser->id;

                // التحقق من أن السمة الأم الجديدة (إذا تم إرسالها) موجودة وتابعة لشركة المستخدم أو super_admin
                if (isset($validatedData['attribute_id']) && $validatedData['attribute_id'] != $attributeValue->attribute_id) {
                    $newAttribute = \App\Models\Attribute::with('company')->find($validatedData['attribute_id']);
                    if (!$newAttribute || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $newAttribute->company_id !== $companyId)) {
                        DB::rollBack();
                        return response()->json(['error' => 'Forbidden', 'message' => 'New parent attribute not found or not accessible within your company.'], 403);
                    }
                }

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه لقيمة السمة.
                $valueCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $attributeValue->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل قيمة سمة لشركة أخرى (فقط سوبر أدمن)
                if ($valueCompanyId != $attributeValue->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You cannot change an attribute value\'s company unless you are a Super Admin.'], 403);
                }

                $validatedData['company_id'] = $valueCompanyId; // تحديث company_id في البيانات المصدقة
                $validatedData['updated_by'] = $updatedBy; // من قام بالتعديل

                $attributeValue->update($validatedData);
                $attributeValue->load($this->relations);
                DB::commit();
                Log::info('Attribute value updated successfully.', ['attribute_value_id' => $attributeValue->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new AttributeValueResource($attributeValue);
            } catch (ValidationException $e) {
                DB::rollBack();
                Log::error('AttributeValue update validation failed: ' . $e->getMessage(), [
                    'errors' => $e->errors(),
                    'user_id' => Auth::id(),
                    'attribute_value_id' => $id,
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
                Log::error('AttributeValue update failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'attribute_value_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error updating attribute value.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('AttributeValue update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'attribute_value_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error updating attribute value.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * حذف قيمة سمة.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $attributeValue = AttributeValue::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true; // المسؤول العام يمكنه حذف أي قيمة سمة
            } elseif ($authUser->hasAnyPermission([perm_key('attribute_values.delete_any'), perm_key('admin.company')])) {
                // يمكنه حذف أي قيمة سمة داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $attributeValue->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.delete_children'))) {
                // يمكنه حذف قيم السمات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attribute_values.delete_self'))) {
                // يمكنه حذف قيمة سمته الخاصة التي أنشأها وتابعة للشركة النشطة
                $canDelete = $attributeValue->belongsToCurrentCompany() && $attributeValue->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this attribute value.'], 403);
            }

            if (!$canDelete) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this attribute value.'], 403);
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كانت قيمة السمة مرتبطة بأي متغيرات منتج (عبر جدول وسيط)
                // افترض أن لديك علاقة productVariants في نموذج AttributeValue
                // ProductVariantAttribute هو جدول وسيط يربط ProductVariant بـ AttributeValue
                if (\App\Models\ProductVariantAttribute::where('attribute_value_id', $attributeValue->id)->exists()) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Conflict',
                        'message' => 'Cannot delete attribute value. It is associated with one or more product variants.',
                    ], 409);
                }

                $attributeValue->delete();
                DB::commit();
                Log::info('Attribute value deleted successfully.', ['attribute_value_id' => $id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(['message' => 'Attribute value deleted successfully'], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('AttributeValue deletion failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'attribute_value_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error deleting attribute value.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('AttributeValue deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'attribute_value_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error deleting attribute value.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}

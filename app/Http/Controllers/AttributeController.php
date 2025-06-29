<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attribute\StoreAttributeRequest;
use App\Http\Requests\Attribute\UpdateAttributeRequest;
use App\Http\Resources\Attribute\AttributeResource;
use App\Models\Attribute; // تم تصحيح الخطأ هنا
use Illuminate\Http\Request; // تم تصحيح الخطأ هنا
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // تم تصحيح الخطأ هنا
use Illuminate\Validation\ValidationException; // تم تصحيح الخطأ هنا
use Throwable; // تم إضافة هذا الاستيراد

// دالة مساعدة لضمان الاتساق في مفاتيح الأذونات (إذا لم تكن معرفة عالميا)
// if (!function_exists('perm_key')) {
//     function perm_key(string $permission): string
//     {
//         return $permission;
//     }
// }

class AttributeController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'values',
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $query = Attribute::with($this->relations);
            $companyId = $authUser->company_id;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // تطبيق منطق الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع السمات (لا قيود إضافية)
            } elseif ($authUser->hasAnyPermission([perm_key('attributes.view_all'), perm_key('admin.company')])) {
                // يرى جميع السمات الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.view_children'))) {
                // يرى السمات التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.view_self'))) {
                // يرى السمات التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view attributes.'], 403);
            }

            // تطبيق فلاتر البحث
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('name', 'like', "%$search%")
                        ->orWhereHas('values', function ($vq) use ($search) {
                            $vq->where('name', 'like', "%$search%")
                                ->orWhere('value', 'like', "%$search%");
                        });
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
            $attributes = $query->paginate($perPage);

            return AttributeResource::collection($attributes)->additional([
                'total' => $attributes->total(),
                'current_page' => $attributes->currentPage(),
                'last_page' => $attributes->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Attribute index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving attributes.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreAttributeRequest $request
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Attribute\AttributeResource
     */
    public function store(StoreAttributeRequest $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // صلاحيات إنشاء سمة
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('attributes.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to create attributes.'
                ], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $attributeCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء سمة لهذه الشركة
                if ($attributeCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You can only create attributes for your current company unless you are a Super Admin.'], 403);
                }

                $validatedData['company_id'] = $attributeCompanyId;
                $validatedData['created_by'] = $authUser->id;

                $attribute = Attribute::find($request->attribute_id); // البحث عن السمة بناءً على attribute_id من الطلب

                if (!$attribute) {
                    // إذا لم يتم العثور على السمة، قم بإنشاء سمة جديدة بالبيانات الأساسية
                    $attribute = Attribute::create([
                        'name' => $validatedData['name'],
                        'company_id' => $validatedData['company_id'],
                        'created_by' => $validatedData['created_by'],
                    ]);
                } else {
                    // إذا تم العثور على السمة، تأكد أنها تابعة لشركة المستخدم الحالي أو أن المستخدم super_admin
                    if (!$authUser->hasPermissionTo(perm_key('admin.super')) && $attribute->company_id !== $companyId) {
                        DB::rollBack();
                        return response()->json(['error' => 'Unauthorized', 'message' => 'You cannot add values to attributes not belonging to your company.'], 403);
                    }
                    // ويمكن هنا تحديث اسم السمة الرئيسية إذا كان مسموحًا (حاليًا لا يوجد في validatedData)
                    // $attribute->update(['name' => $validatedData['name']]);
                }

                // حفظ قيم السمة (AttributeValues)
                if (!empty($validatedData['values']) && is_array($validatedData['values'])) {
                    foreach ($validatedData['values'] as $valueData) {
                        $attribute->values()->create([
                            'name' => $valueData['name'],
                            'value' => $valueData['value'] ?? null, // تأكد من وجود حقل 'value'
                            'company_id' => $attributeCompanyId, // ربط بنفس شركة السمة الأم
                            'created_by' => $authUser->id, // من أنشأ قيمة السمة
                        ]);
                    }
                } elseif (!empty($validatedData['name_value'])) { // للحالة التي ترسل فيها قيمة واحدة مباشرة
                    $attribute->values()->create([
                        'name' => $validatedData['name_value'],
                        'value' => $validatedData['value'] ?? null,
                        'company_id' => $attributeCompanyId,
                        'created_by' => $authUser->id,
                    ]);
                }


                DB::commit();
                Log::info('Attribute and/or its values created successfully.', ['attribute_id' => $attribute->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new AttributeResource($attribute->load($this->relations)); // تحميل العلاقات للعرض
            } catch (ValidationException $e) {
                DB::rollBack();
                Log::error('Attribute store validation failed: ' . $e->getMessage(), [
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
                Log::error('Attribute store failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error saving attribute.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Attribute store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error saving attribute.',
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
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Attribute\AttributeResource
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

            $attribute = Attribute::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي سمة
            } elseif ($authUser->hasAnyPermission([perm_key('attributes.view_all'), perm_key('admin.company')])) {
                // يرى إذا كانت السمة تنتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $attribute->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.view_children'))) {
                // يرى إذا كانت السمة أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canView = $attribute->belongsToCurrentCompany() && $attribute->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.view_self'))) {
                // يرى إذا كانت السمة أنشأها هو وتابعة للشركة النشطة
                $canView = $attribute->belongsToCurrentCompany() && $attribute->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this attribute.'], 403);
            }

            if ($canView) {
                return new AttributeResource($attribute);
            }

            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this attribute.'], 403);
        } catch (Throwable $e) {
            Log::error('Attribute show failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'attribute_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving attribute.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateAttributeRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Attribute\AttributeResource
     */
    public function update(UpdateAttributeRequest $request, string $id)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $attribute = Attribute::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي سمة
            } elseif ($authUser->hasAnyPermission([perm_key('attributes.update_any'), perm_key('admin.company')])) {
                // يمكنه تعديل أي سمة داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $attribute->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.update_children'))) {
                // يمكنه تعديل السمات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $attribute->belongsToCurrentCompany() && $attribute->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.update_self'))) {
                // يمكنه تعديل سمته الخاصة التي أنشأها وتابعة للشركة النشطة
                $canUpdate = $attribute->belongsToCurrentCompany() && $attribute->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this attribute.'], 403);
            }

            if (!$canUpdate) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this attribute.'], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $updatedBy = $authUser->id;

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للسمة.
                $attributeCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $attribute->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل سمة لشركة أخرى (فقط سوبر أدمن)
                if ($attributeCompanyId != $attribute->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You cannot change an attribute\'s company unless you are a Super Admin.'], 403);
                }
                $validatedData['company_id'] = $attributeCompanyId;

                $attribute->update([
                    'name' => $validatedData['name'],
                    'company_id' => $validatedData['company_id'],
                    'updated_by' => $updatedBy,
                ]);

                // تحديث أو إنشاء قيم السمات (AttributeValues)
                $requestedValueIds = collect($validatedData['values'] ?? [])->pluck('id')->filter()->all();
                $attribute->values()->whereNotIn('id', $requestedValueIds)->delete(); // حذف القيم غير المرسلة

                if (!empty($validatedData['values']) && is_array($validatedData['values'])) {
                    foreach ($validatedData['values'] as $valueData) {
                        $attribute->values()->updateOrCreate(
                            ['id' => $valueData['id'] ?? null], // إذا كان هناك ID يتم تحديثه، وإلا يتم إنشاء جديد
                            [
                                'name' => $valueData['name'],
                                'value' => $valueData['value'] ?? null,
                                'company_id' => $attributeCompanyId, // ربط بنفس شركة السمة الأم
                                'created_by' => $valueData['created_by'] ?? $authUser->id,
                                'updated_by' => $authUser->id,
                            ]
                        );
                    }
                }

                DB::commit();
                Log::info('Attribute updated successfully.', ['attribute_id' => $attribute->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new AttributeResource($attribute->load($this->relations)); // تحميل العلاقات للعرض
            } catch (ValidationException $e) {
                DB::rollBack();
                Log::error('Attribute update validation failed: ' . $e->getMessage(), [
                    'errors' => $e->errors(),
                    'user_id' => Auth::id(),
                    'attribute_id' => $id,
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
                Log::error('Attribute update failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'attribute_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error updating attribute.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Attribute update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'attribute_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error updating attribute.',
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
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $attribute = Attribute::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true; // المسؤول العام يمكنه حذف أي سمة
            } elseif ($authUser->hasAnyPermission([perm_key('attributes.delete_any'), perm_key('admin.company')])) {
                // يمكنه حذف أي سمة داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $attribute->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.delete_children'))) {
                // يمكنه حذف السمات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $attribute->belongsToCurrentCompany() && $attribute->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.delete_self'))) {
                // يمكنه حذف سمته الخاصة التي أنشأها وتابعة للشركة النشطة
                $canDelete = $attribute->belongsToCurrentCompany() && $attribute->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this attribute.'], 403);
            }

            if (!$canDelete) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this attribute.'], 403);
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كانت السمة مرتبطة بأي متغيرات منتج قبل الحذف
                if ($attribute->productVariants()->exists()) { // افترض أن لديك علاقة productVariants في نموذج Attribute
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Conflict',
                        'message' => 'Cannot delete attribute. It is associated with one or more product variants.',
                    ], 409);
                }

                // حذف قيم السمة المرتبطة
                $attribute->values()->delete();
                $attribute->delete();

                DB::commit();
                Log::info('Attribute deleted successfully.', ['attribute_id' => $id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(['message' => 'Attribute deleted successfully'], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Attribute deletion failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'attribute_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error deleting attribute.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Attribute deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'attribute_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error deleting attribute.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}

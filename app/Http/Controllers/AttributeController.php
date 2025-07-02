<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attribute\StoreAttributeRequest;
use App\Http\Requests\Attribute\UpdateAttributeRequest;
use App\Http\Resources\Attribute\AttributeResource;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AttributeController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'values',
            'company',
            'creator',
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
            $query = Attribute::with($this->relations);
            $companyId = $authUser->company_id ?? null;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
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
                return api_forbidden('ليس لديك إذن لعرض السمات.');
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

            return api_success($attributes, 'تم استرداد السمات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تخزين مورد تم إنشاؤه حديثًا في التخزين.
     *
     * @param StoreAttributeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreAttributeRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء سمة
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('attributes.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء سمات.');
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
                    return api_forbidden('يمكنك فقط إنشاء سمات لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
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
                        return api_forbidden('لا يمكنك إضافة قيم إلى سمات لا تنتمي إلى شركتك.');
                    }
                }

                // حفظ قيم السمة (AttributeValues)
                if (!empty($validatedData['values']) && is_array($validatedData['values'])) {
                    foreach ($validatedData['values'] as $valueData) {
                        $attribute->values()->create([
                            'name' => $valueData['name'],
                            'value' => $valueData['value'] ?? null,
                            'company_id' => $attributeCompanyId,
                            'created_by' => $authUser->id,
                        ]);
                    }
                } elseif (!empty($validatedData['name_value'])) {
                    $attribute->values()->create([
                        'name' => $validatedData['name_value'],
                        'value' => $validatedData['value'] ?? null,
                        'company_id' => $attributeCompanyId,
                        'created_by' => $authUser->id,
                    ]);
                }

                DB::commit();
                // إرجاع المورد الذي تم إنشاؤه
                return api_success(new AttributeResource($attribute->load($this->relations)), 'تم إنشاء السمة وقيمها بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين السمة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ السمة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * عرض المورد المحدد.
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

            $attribute = Attribute::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('attributes.view_all'), perm_key('admin.company')])) {
                $canView = $attribute->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.view_children'))) {
                $canView = $attribute->belongsToCurrentCompany() && $attribute->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.view_self'))) {
                $canView = $attribute->belongsToCurrentCompany() && $attribute->createdByCurrentUser();
            }

            if ($canView) {
                // إرجاع المورد الذي تم عرضه
                return api_success(new AttributeResource($attribute), 'تم استرداد السمة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه السمة.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تحديث المورد المحدد في التخزين.
     *
     * @param UpdateAttributeRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateAttributeRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $attribute = Attribute::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('attributes.update_all'), perm_key('admin.company')])) {
                $canUpdate = $attribute->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.update_children'))) {
                $canUpdate = $attribute->belongsToCurrentCompany() && $attribute->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.update_self'))) {
                $canUpdate = $attribute->belongsToCurrentCompany() && $attribute->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذه السمة.');
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
                    return api_forbidden('لا يمكنك تغيير شركة السمة ما لم تكن مسؤولاً عامًا.');
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
                            ['id' => $valueData['id'] ?? null],
                            [
                                'name' => $valueData['name'],
                                'value' => $valueData['value'] ?? null,
                                'company_id' => $attributeCompanyId,
                                'created_by' => $valueData['created_by'] ?? $authUser->id,
                                'updated_by' => $authUser->id,
                            ]
                        );
                    }
                }

                DB::commit();
                // إرجاع المورد الذي تم تحديثه
                return api_success(new AttributeResource($attribute->load($this->relations)), 'تم تحديث السمة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث السمة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث السمة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * حذف المورد المحدد من التخزين.
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

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $attribute = Attribute::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('attributes.delete_all'), perm_key('admin.company')])) {
                $canDelete = $attribute->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.delete_children'))) {
                $canDelete = $attribute->belongsToCurrentCompany() && $attribute->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('attributes.delete_self'))) {
                $canDelete = $attribute->belongsToCurrentCompany() && $attribute->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذه السمة.');
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كانت السمة مرتبطة بأي متغيرات منتج قبل الحذف
                if ($attribute->productVariants()->exists()) { // افترض أن لديك علاقة productVariants في نموذج Attribute
                    DB::rollBack();
                    return api_error('لا يمكن حذف السمة. إنها مرتبطة بمتغير واحد أو أكثر من متغيرات المنتج.', [], 409);
                }

                // حفظ نسخة من السمة قبل حذفها لإرجاعها في الاستجابة
                $deletedAttribute = $attribute->replicate();
                $deletedAttribute->setRelation('values', $attribute->values); // حفظ القيم المرتبطة أيضًا

                // حذف قيم السمة المرتبطة
                $attribute->values()->delete();
                $attribute->delete();

                DB::commit();
                // إرجاع المورد الذي تم حذفه
                return api_success(new AttributeResource($deletedAttribute->load($this->relations)), 'تم حذف السمة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف السمة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\ProductVariant\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariant\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductVariantController extends Controller
{
    protected array $relations = [
        'creator',
        'company', // علاقة مباشرة بالشركة للمتغير
        'product', // المنتج الأم
        'product.creator',
        'product.company',
        'product.category',
        'product.brand',
        'attributes.attribute',
        'attributes.attributeValue',
        'stocks.warehouse',
    ];

    /**
     * عرض قائمة بمتغيرات المنتجات مع الفلاتر والصلاحيات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = ProductVariant::with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة أو لديك صلاحية مدير عام.');
            }

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع المتغيرات
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض متغيرات المنتجات.');
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('sku', 'like', "%$search%")
                        ->orWhere('barcode', 'like', "%$search%")
                        ->orWhereHas('product', function ($pq) use ($search) {
                            $pq->where('name', 'like', "%$search%")
                                ->orWhere('desc', 'like', "%$search%");
                        });
                });
            }
            if ($request->filled('product_id')) {
                $query->where('product_id', $request->input('product_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $perPage = max(1, (int) $request->get('per_page', 10));
            $sortField = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $productVariants = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            return api_success($productVariants, 'تم جلب متغيرات المنتجات بنجاح');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreProductVariantRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProductVariantRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء متغير منتج
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('product_variants.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك صلاحية لإنشاء متغيرات المنتجات.');
            }

            DB::beginTransaction();
            try {
                $validated = $request->validated();

                // التحقق من أن المنتج الأم تابع لشركة المستخدم أو أن المستخدم super_admin
                $product = Product::with('company')->find($validated['product_id']);
                if (!$product || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $product->company_id !== $companyId)) {
                    DB::rollBack();
                    return api_forbidden('المنتج غير موجود أو غير متاح ضمن شركتك.');
                }

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمنتج الأم.
                $variantCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validated['company_id']))
                    ? $validated['company_id']
                    : $product->company_id; // استخدم company_id للمنتج الأم

                // التأكد من أن المستخدم مصرح له بإنشاء متغير لهذه الشركة
                if ($variantCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء متغيرات منتجات لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }

                $validated['company_id'] = $variantCompanyId;
                $validated['created_by'] = $authUser->id; // من أنشأ المتغير

                $productVariant = ProductVariant::create($validated);

                // حفظ الخصائص (attributes)
                if ($request->has('attributes') && is_array($request->input('attributes'))) {
                    foreach ($request->input('attributes') as $attr) {
                        if (!empty($attr['attribute_id']) && !empty($attr['attribute_value_id'])) {
                            $productVariant->attributes()->create([
                                'attribute_id' => $attr['attribute_id'],
                                'attribute_value_id' => $attr['attribute_value_id'],
                                'company_id' => $variantCompanyId, // ربط بنفس شركة المتغير
                                'created_by' => $authUser->id, // من أنشأ الخاصية
                            ]);
                        }
                    }
                }

                // حفظ المخزون (stocks)
                if ($request->has('stocks') && is_array($request->input('stocks'))) {
                    foreach ($request->input('stocks') as $stockData) {
                        if (!empty($stockData['warehouse_id'])) {
                            Stock::create([
                                'product_variant_id' => $productVariant->id,
                                'warehouse_id' => $stockData['warehouse_id'],
                                'quantity' => $stockData['quantity'] ?? 0,
                                'reserved' => $stockData['reserved'] ?? 0,
                                'min_quantity' => $stockData['min_quantity'] ?? 0,
                                'cost' => $stockData['cost'] ?? null,
                                'batch' => $stockData['batch'] ?? null,
                                'expiry' => $stockData['expiry'] ?? null,
                                'loc' => $stockData['loc'] ?? null,
                                'status' => $stockData['status'] ?? 'available',
                                'company_id' => $variantCompanyId, // ربط بنفس شركة المتغير
                                'created_by' => $authUser->id, // من أنشأ سجل المخزون
                            ]);
                        }
                    }
                }

                DB::commit();
                return api_success(new ProductVariantResource($productVariant->load($this->relations)), 'تم إنشاء متغير المنتج بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين متغير المنتج.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ متغير المنتج.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
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

            $productVariant = ProductVariant::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي متغير
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.view_all'), perm_key('admin.company')])) {
                // يرى إذا كان المتغير ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $productVariant->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_children'))) {
                // يرى إذا كان المتغير أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $productVariant->belongsToCurrentCompany() && $productVariant->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.view_self'))) {
                // يرى إذا كان المتغير أنشأه هو وتابع للشركة النشطة
                $canView = $productVariant->belongsToCurrentCompany() && $productVariant->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new ProductVariantResource($productVariant), 'تم استرداد متغير المنتج بنجاح.');
            }

            return api_forbidden('ليس لديك صلاحية لعرض متغير المنتج هذا.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateProductVariantRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProductVariantRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $productVariant = ProductVariant::with(['company', 'creator', 'product'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي متغير
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.update_all'), perm_key('admin.company')])) {
                // يمكنه تعديل أي متغير داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $productVariant->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.update_children'))) {
                // يمكنه تعديل المتغيرات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $productVariant->belongsToCurrentCompany() && $productVariant->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.update_self'))) {
                // يمكنه تعديل متغيره الخاص الذي أنشأه وتابع للشركة النشطة
                $canUpdate = $productVariant->belongsToCurrentCompany() && $productVariant->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك صلاحية لتحديث متغير المنتج هذا.');
            }

            DB::beginTransaction();
            try {
                $validated = $request->validated();
                $updatedBy = $authUser->id;

                // التحقق من أن المنتج الأم المحدث (إذا تم إرساله) تابع لشركة المستخدم أو أن المستخدم super_admin
                if (isset($validated['product_id']) && $validated['product_id'] != $productVariant->product_id) {
                    $newProduct = Product::with('company')->find($validated['product_id']);
                    if (!$newProduct || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $newProduct->company_id !== $companyId)) {
                        DB::rollBack();
                        return api_forbidden('المنتج الجديد غير موجود أو غير متاح ضمن شركتك.');
                    }
                }

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للمتغير.
                $variantCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validated['company_id']))
                    ? $validated['company_id']
                    : $productVariant->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل متغير لشركة أخرى (فقط سوبر أدمن)
                if ($variantCompanyId != $productVariant->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة متغير المنتج إلا إذا كنت مدير عام.');
                }

                $validated['company_id'] = $variantCompanyId; // تحديث company_id في البيانات المصدقة
                $validated['updated_by'] = $updatedBy; // من قام بالتعديل

                $productVariant->update($validated);

                // تحديث الخصائص (attributes)
                $requestedAttributeIds = collect($request->input('attributes') ?? [])
                    ->pluck('id')->filter()->all();
                $productVariant->attributes()->whereNotIn('id', $requestedAttributeIds)->delete(); // حذف الخصائص المحذوفة

                if ($request->has('attributes') && is_array($request->input('attributes'))) {
                    foreach ($request->input('attributes') as $attr) {
                        if (!empty($attr['attribute_id']) && !empty($attr['attribute_value_id'])) {
                            // التحقق من وجود الخاصية وتحديثها أو إنشائها
                            $productVariant->attributes()->updateOrCreate(
                                ['id' => $attr['id'] ?? null], // إذا كان هناك ID يتم تحديثه، وإلا يتم إنشاء جديد
                                [
                                    'attribute_id' => $attr['attribute_id'],
                                    'attribute_value_id' => $attr['attribute_value_id'],
                                    'company_id' => $variantCompanyId, // ربط بنفس شركة المتغير
                                    'created_by' => $attr['created_by'] ?? $authUser->id, // إذا تم تحديدها أو المستخدم الحالي
                                    'updated_by' => $authUser->id,
                                ]
                            );
                        }
                    }
                }

                // تحديث سجلات المخزون (stocks)
                $requestedStockIds = collect($request->input('stocks') ?? [])->pluck('id')->filter()->all();
                $productVariant->stocks()->whereNotIn('id', $requestedStockIds)->delete(); // حذف سجلات المخزون المحذوفة

                if ($request->has('stocks') && is_array($request->input('stocks'))) {
                    foreach ($request->input('stocks') as $stockData) {
                        if (!empty($stockData['warehouse_id'])) {
                            Stock::updateOrCreate(
                                ['id' => $stockData['id'] ?? null, 'product_variant_id' => $productVariant->id],
                                [
                                    'warehouse_id' => $stockData['warehouse_id'],
                                    'quantity' => $stockData['quantity'] ?? 0,
                                    'reserved' => $stockData['reserved'] ?? 0,
                                    'min_quantity' => $stockData['min_quantity'] ?? 0,
                                    'cost' => $stockData['cost'] ?? null,
                                    'batch' => $stockData['batch'] ?? null,
                                    'expiry' => $stockData['expiry'] ?? null,
                                    'loc' => $stockData['loc'] ?? null,
                                    'status' => $stockData['status'] ?? 'available',
                                    'company_id' => $variantCompanyId, // ربط بنفس شركة المتغير
                                    'created_by' => $stockData['created_by'] ?? $authUser->id,
                                    'updated_by' => $authUser->id,
                                ]
                            );
                        }
                    }
                } else {
                    $productVariant->stocks()->delete(); // حذف كل المخزون إذا لم يتم إرسال أي بيانات
                }


                DB::commit();
                return api_success(new ProductVariantResource($productVariant->load($this->relations)), 'تم تحديث متغير المنتج بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث متغير المنتج.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث متغير المنتج.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
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

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $productVariant = ProductVariant::with(['company', 'creator', 'stocks', 'attributes'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true; // المسؤول العام يمكنه حذف أي متغير
            } elseif ($authUser->hasAnyPermission([perm_key('product_variants.delete_all'), perm_key('admin.company')])) {
                // يمكنه حذف أي متغير داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $productVariant->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.delete_children'))) {
                // يمكنه حذف المتغيرات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $productVariant->belongsToCurrentCompany() && $productVariant->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('product_variants.delete_self'))) {
                // يمكنه حذف متغيره الخاص الذي أنشأه وتابع للشركة النشطة
                $canDelete = $productVariant->belongsToCurrentCompany() && $productVariant->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك صلاحية لحذف متغير المنتج هذا.');
            }

            DB::beginTransaction();
            try {
                // حفظ نسخة من المتغير قبل حذفه لإرجاعها في الاستجابة
                $deletedProductVariant = $productVariant->replicate();
                $deletedProductVariant->setRelations($productVariant->getRelations());

                // حذف العلاقات التابعة لـ ProductVariant (Stocks, Attributes) أولاً
                $productVariant->stocks()->delete();
                $productVariant->attributes()->delete();
                $productVariant->delete();

                DB::commit();
                return api_success(new ProductVariantResource($deletedProductVariant), 'تم حذف متغير المنتج بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف متغير المنتج.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * البحث عن متغيرات منتج باستخدام براميتر بحث وتطبيق الصلاحيات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchByProduct(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $search = $request->get('search');
            // إذا كان البحث فارغًا أو قصيرًا جدًا، ارجع مجموعة فارغة
            if (empty($search) || mb_strlen($search) <= 2) {
                return api_success([], 'لا توجد نتائج بحث.');
            }

            $productQuery = Product::query();

            // تطبيق منطق الصلاحيات على استعلام المنتج
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع المنتجات
            } elseif ($authUser->hasAnyPermission([perm_key('products.view_all'), perm_key('admin.company'), perm_key('product_variants.view_all')])) { // يجب أن يكون لديه صلاحية رؤية المنتجات أو المتغيرات
                $productQuery->whereCompanyIsCurrent();
            } elseif ($authUser->hasAnyPermission([perm_key('products.view_children'), perm_key('product_variants.view_children')])) {
                $productQuery->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasAnyPermission([perm_key('products.view_self'), perm_key('product_variants.view_self')])) {
                $productQuery->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                // إذا لم يكن لديه صلاحية رؤية أي منتج أو متغير، ارجع مجموعة فارغة
                return api_forbidden('ليس لديك صلاحية لعرض المنتجات أو متغيراتها.');
            }

            $productQuery->where(function ($q) use ($search) {
                $q
                    ->where('name', 'like', "%$search%")
                    ->orWhere('desc', 'like', "%$search%");
            });

            $perPage = max(1, (int) $request->get('per_page', 10));

            $productsWithVariants = $productQuery->with(['variants' => function ($query) {
                // تحميل العلاقات المطلوبة للمتغيرات
                $query->with($this->relations);
            }])->paginate($perPage);

            // استخلاص جميع المتغيرات من المنتجات المفلترة
            $variants = collect($productsWithVariants->items())->flatMap(function ($product) {
                return $product->variants;
            });
            // إذا لم توجد متغيرات، ارجع مجموعة فارغة
            return api_success(ProductVariantResource::collection($variants), 'تم العثور على متغيرات المنتجات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

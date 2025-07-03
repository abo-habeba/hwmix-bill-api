<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Class ProductController
 *
 * تحكم في عمليات المنتجات (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class ProductController extends Controller
{
    /**
     * العلاقات الافتراضية المستخدمة مع المنتجات
     * @var array
     */
    protected array $relations = [
        'company',
        'creator',
        'category',
        'brand',
        'variants',
        'variants.attributes.attribute',
        'variants.attributes.attributeValue',
        'variants.stocks.warehouse',
    ];

    /**
     * عرض قائمة المنتجات مع الفلاتر والصلاحيات.
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

            $query = Product::with($this->relations);
            $companyId = $authUser->company_id ?? null; // معرف الشركة النشطة للمستخدم

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة أو لديك صلاحية مدير عام.');
            }

            // تطبيق منطق الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع المنتجات (لا توجد قيود إضافية على الاستعلام)
            } elseif ($authUser->hasAnyPermission([perm_key('products.view_all'), perm_key('admin.company')])) {
                // يرى جميع المنتجات الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('products.view_children'))) {
                // يرى المنتجات التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('products.view_self'))) {
                // يرى المنتجات التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                // إذا لم يكن لديه أي صلاحية رؤية عامة، ارجع خطأ Forbidden
                return api_forbidden('ليس لديك صلاحية لعرض المنتجات.');
            }

            // تطبيق فلاتر البحث
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('name', 'like', "%$search%")
                        ->orWhere('desc', 'like', "%$search%")
                        ->orWhere('slug', 'like', "%$search%")
                        ->orWhereHas('category', function ($q) use ($search) {
                            $q
                                ->where('name', 'like', "%$search%")
                                ->orWhere('desc', 'like', "%$search%");
                        })
                        ->orWhereHas('brand', function ($q) use ($search) {
                            $q
                                ->where('name', 'like', "%$search%")
                                ->orWhere('desc', 'like', "%$search%");
                        });
                });
            }
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->input('category_id'));
            }
            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->input('brand_id'));
            }
            if ($request->filled('active')) {
                $query->where('active', (bool) $request->input('active'));
            }
            if ($request->filled('featured')) {
                $query->where('featured', (bool) $request->input('featured'));
            }


            // الفرز والتصفح
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = max(1, (int) $request->input('per_page', 10));
            $products = $query->paginate($perPage);

            return api_success($products, 'تم جلب المنتجات بنجاح');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreProductRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة.');
            }

            // صلاحيات إنشاء منتج
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('products.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك صلاحية لإنشاء المنتجات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $validatedData['company_id'] = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء منتج لهذه الشركة
                if ($validatedData['company_id'] != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء منتجات لشركتك النشطة.');
                }

                $validatedData['created_by'] = $authUser->id;
                $validatedData['active'] = (bool) ($validatedData['active'] ?? false);
                $validatedData['featured'] = (bool) ($validatedData['featured'] ?? false);
                $validatedData['returnable'] = (bool) ($validatedData['returnable'] ?? false);
                $validatedData['slug'] = Product::generateSlug($validatedData['name']);

                $product = Product::create($validatedData);

                if ($request->has('variants') && is_array($request->input('variants'))) {
                    foreach ($request->input('variants') as $variantData) {
                        $variantCreateData = collect($variantData)->except(['attributes', 'stocks'])->toArray();
                        $variantCreateData['company_id'] = $validatedData['company_id']; // تأكد من ربطها بنفس شركة المنتج
                        $variantCreateData['created_by'] = $validatedData['created_by'];

                        $variant = $product->variants()->create($variantCreateData);

                        if (!empty($variantData['attributes']) && is_array($variantData['attributes'])) {
                            foreach ($variantData['attributes'] as $attributeData) {
                                if (empty($attributeData['attribute_id']) || empty($attributeData['attribute_value_id'])) {
                                    continue;
                                }
                                $variant->attributes()->create([
                                    'attribute_id' => $attributeData['attribute_id'],
                                    'attribute_value_id' => $attributeData['attribute_value_id'],
                                    'company_id' => $validatedData['company_id'], // تأكد من ربطها بنفس شركة المنتج
                                    'created_by' => $validatedData['created_by'],
                                ]);
                            }
                        }

                        if (!empty($variantData['stocks']) && is_array($variantData['stocks'])) {
                            foreach ($variantData['stocks'] as $stockData) {
                                $stockCreateData = [
                                    'quantity' => $stockData['quantity'] ?? 0,
                                    'reserved' => $stockData['reserved'] ?? 0,
                                    'min_quantity' => $stockData['min_quantity'] ?? 0,
                                    'cost' => $stockData['cost'] ?? null,
                                    'batch' => $stockData['batch'] ?? null,
                                    'expiry' => $stockData['expiry'] ?? null,
                                    'loc' => $stockData['loc'] ?? null,
                                    'status' => $stockData['status'] ?? 'available',
                                    'warehouse_id' => $stockData['warehouse_id'] ?? null,
                                    'company_id' => $validatedData['company_id'], // تأكد من ربطها بنفس شركة المنتج
                                    'created_by' => $validatedData['created_by'],
                                ];
                                $variant->stocks()->create($stockCreateData);
                            }
                        }
                    }
                }

                DB::commit();

                return api_success(ProductResource::make($product->load($this->relations)), 'تم إنشاء المنتج بنجاح');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين المنتج.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ المنتج.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Product $product): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة.');
            }

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي منتج
            } elseif ($authUser->hasAnyPermission([perm_key('products.view_all'), perm_key('admin.company')])) {
                // يرى إذا كان المنتج ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $product->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('products.view_children'))) {
                // يرى إذا كان المنتج أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $product->belongsToCurrentCompany() && $product->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('products.view_self'))) {
                // يرى إذا كان المنتج أنشأه هو وتابع للشركة النشطة
                $canView = $product->belongsToCurrentCompany() && $product->createdByCurrentUser();
            }

            if ($canView) {
                $product->load($this->relations); // تحميل العلاقات فقط إذا كان مصرحًا له
                return api_success(ProductResource::make($product), 'تم جلب بيانات المنتج بنجاح');
            }

            return api_forbidden('ليس لديك صلاحية لعرض هذا المنتج.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateProductRequest $request
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة.');
            }

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي منتج
            } elseif ($authUser->hasAnyPermission([perm_key('products.update_all'), perm_key('admin.company')])) {
                // يمكنه تعديل أي منتج داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $product->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('products.update_children'))) {
                // يمكنه تعديل المنتجات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $product->belongsToCurrentCompany() && $product->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('products.update_self'))) {
                // يمكنه تعديل منتجه الخاص الذي أنشأه وتابع للشركة النشطة
                $canUpdate = $product->belongsToCurrentCompany() && $product->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك صلاحية لتحديث هذا المنتج.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $updatedBy = $authUser->id;

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للمنتج.
                $validatedData['company_id'] = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $product->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل منتج لهذه الشركة
                if ($validatedData['company_id'] != $product->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة المنتج إلا إذا كنت مدير عام.');
                }

                $validatedData['active'] = (bool) ($validatedData['active'] ?? $product->active); // احتفظ بالقيمة الحالية إذا لم ترسل
                $validatedData['featured'] = (bool) ($validatedData['featured'] ?? $product->featured);
                $validatedData['returnable'] = (bool) ($validatedData['returnable'] ?? $product->returnable);
                $validatedData['slug'] = $validatedData['slug'] ?? Product::generateSlug($validatedData['name']);

                $productData = [
                    'name' => $validatedData['name'],
                    'slug' => $validatedData['slug'],
                    'desc' => $validatedData['desc'] ?? null,
                    'desc_long' => $validatedData['desc_long'] ?? null,
                    'published_at' => $validatedData['published_at'] ?? null,
                    'category_id' => $validatedData['category_id'],
                    'brand_id' => $validatedData['brand_id'] ?? null,
                    'company_id' => $validatedData['company_id'],
                    'active' => $validatedData['active'],
                    'featured' => $validatedData['featured'],
                    'returnable' => $validatedData['returnable'],
                ];

                $product->update($productData);

                // معالجة المتغيرات (Variants)
                $requestedVariantIds = collect($validatedData['variants'] ?? [])->pluck('id')->filter()->all();
                $product->variants()->whereNotIn('id', $requestedVariantIds)->delete();

                if (!empty($validatedData['variants']) && is_array($validatedData['variants'])) {
                    foreach ($validatedData['variants'] as $variantData) {
                        $variantCreateUpdateData = [
                            'barcode' => $variantData['barcode'] ?? null,
                            'sku' => $variantData['sku'] ?? null,
                            'retail_price' => $variantData['retail_price'] ?? null,
                            'wholesale_price' => $variantData['wholesale_price'] ?? null,
                            'image' => $variantData['image'] ?? null,
                            'weight' => $variantData['weight'] ?? null,
                            'dimensions' => $variantData['dimensions'] ?? null,
                            'min_quantity' => $variantData['min_quantity'] ?? null,
                            'tax' => $variantData['tax'] ?? null,
                            'discount' => $variantData['discount'] ?? null,
                            'status' => $variantData['status'] ?? 'active',
                            'company_id' => $validatedData['company_id'], // استخدام company_id للمنتج
                            'created_by' => $variantData['created_by'] ?? $authUser->id,
                        ];

                        $variant = ProductVariant::updateOrCreate(
                            ['id' => $variantData['id'] ?? null, 'product_id' => $product->id],
                            $variantCreateUpdateData
                        );

                        // معالجة خصائص المتغير (Attributes)
                        $requestedAttributeIds = collect($variantData['attributes'] ?? [])
                            ->filter(fn($attr) => isset($attr['attribute_id']) && isset($attr['attribute_value_id']))
                            ->map(fn($attr) => [
                                'attribute_id' => $attr['attribute_id'],
                                'attribute_value_id' => $attr['attribute_value_id'],
                                'company_id' => $validatedData['company_id'], // استخدام company_id للمنتج
                                'created_by' => $authUser->id, // منشئ الـ attribute هو المستخدم الحالي
                            ])
                            ->all();

                        $variant->attributes()->delete(); // حذف القديم وإعادة الإنشاء
                        if (!empty($requestedAttributeIds)) {
                            $variant->attributes()->createMany($requestedAttributeIds);
                        }

                        // معالجة سجلات المخزون (Stocks)
                        $requestedStockIds = collect($variantData['stocks'] ?? [])->pluck('id')->filter()->all();
                        $variant->stocks()->whereNotIn('id', $requestedStockIds)->delete();

                        if (!empty($variantData['stocks']) && is_array($variantData['stocks'])) {
                            foreach ($variantData['stocks'] as $stockData) {
                                $stockCreateUpdateData = [
                                    'quantity' => $stockData['quantity'] ?? 0,
                                    'reserved' => $stockData['reserved'] ?? 0,
                                    'min_quantity' => $stockData['min_quantity'] ?? 0,
                                    'cost' => $stockData['cost'] ?? null,
                                    'batch' => $stockData['batch'] ?? null,
                                    'expiry' => $stockData['expiry'] ?? null,
                                    'loc' => $stockData['loc'] ?? null,
                                    'status' => $stockData['status'] ?? 'available',
                                    'warehouse_id' => $stockData['warehouse_id'] ?? null,
                                    'company_id' => $validatedData['company_id'], // استخدام company_id للمنتج
                                    'created_by' => $stockData['created_by'] ?? $authUser->id,
                                    'updated_by' => $updatedBy,
                                    'variant_id' => $variant->id,
                                ];

                                Stock::updateOrCreate(
                                    ['id' => $stockData['id'] ?? null, 'variant_id' => $variant->id],
                                    $stockCreateUpdateData
                                );
                            }
                        } else {
                            $variant->stocks()->delete(); // حذف المخزون إذا لم يتم إرسال بيانات له
                        }
                    }
                } else {
                    $product->variants()->delete(); // حذف جميع المتغيرات إذا لم يتم إرسال بيانات لها
                }

                DB::commit();

                return api_success(ProductResource::make($product->load($this->relations)), 'تم تحديث المنتج بنجاح');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث المنتج.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث المنتج.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يجب أن تكون مرتبطًا بشركة.');
            }

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true; // المسؤول العام يمكنه حذف أي منتج
            } elseif ($authUser->hasAnyPermission([perm_key('products.delete_all'), perm_key('admin.company')])) {
                // يمكنه حذف أي منتج داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $product->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('products.delete_children'))) {
                // يمكنه حذف المنتجات التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $product->belongsToCurrentCompany() && $product->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('products.delete_self'))) {
                // يمكنه حذف منتجه الخاص الذي أنشأه وتابع للشركة النشطة
                $canDelete = $product->belongsToCurrentCompany() && $product->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك صلاحية لحذف هذا المنتج.');
            }

            DB::beginTransaction();
            try {
                // حفظ نسخة من المنتج قبل حذفه لإرجاعها في الاستجابة
                $deletedProduct = $product->replicate();
                $deletedProduct->setRelations($product->getRelations()); // نسخ العلاقات المحملة

                // حذف المتغيرات المتعلقة، والتي بدورها ستحذف سجلات المخزون والخصائص
                // يجب التأكد من ضبط cascade deletes في قاعدة البيانات أو حذفها يدوياً بترتيب صحيح
                foreach ($product->variants as $variant) {
                    $variant->attributes()->delete();
                    $variant->stocks()->delete();
                    $variant->delete();
                }
                $product->delete();

                DB::commit();
                return api_success(ProductResource::make($deletedProduct), 'تم حذف المنتج بنجاح');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف المنتج.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

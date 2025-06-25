<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;  // استخدم Throwable للتعامل الشامل مع الأخطاء والاستثناءات

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
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $authUser */
        try {
            $authUser = Auth::user();

            if (!$authUser) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required.'
                ], 401);
            }

            $query = Product::with($this->relations);
            /** @var \App\Models\User $authUser */
            // تطبيق منطق الصلاحيات
            if ($authUser->hasAnyPermission(['products_all', 'super_admin'])) {
                // إذا كان لديه صلاحية 'products_all' أو 'super_admin'، لا حاجة لتطبيق أي scope خاص
            } elseif ($authUser->hasPermissionTo('company_owner')) {
                // إذا كان مالك شركة، طبق scopeCompany لجلب منتجات شركته فقط
                $query->scopeCompany();
            } elseif ($authUser->hasPermissionTo('products_all_own')) {
                // إذا كان لديه صلاحية 'products_all_own'، طبق scopeOwn لجلب المنتجات التي أنشأها هو
                $query->scopeOwn();
            } elseif ($authUser->hasPermissionTo('products_all_self')) {
                // إذا كان لديه صلاحية 'products_all_self'، طبق scopeSelf لجلب المنتجات الخاصة به
                $query->scopeSelf();
            } else {
                // إذا لم يكن لديه أي صلاحية رؤية عامة، ارجع خطأ Unauthorized
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to view products.'
                ], 403);
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

            // الفرز والتصفح
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->input('per_page', 10);
            $products = $query->paginate($perPage);

            return ProductResource::collection($products)->additional([
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]);
        } catch (Throwable $e) {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            // تسجيل الخطأ وتفاصيله
            Log::error('Product index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $authUser->id,  // إضافة ID المستخدم للمساعدة في تتبع المشاكل
            ]);
            return response()->json([
                'error' => true,
                'message' => 'حدث خطأ أثناء جلب المنتجات.',
                // 'details' => $e->getMessage(), // يمكن إخفاء هذه التفاصيل في بيئة الإنتاج
                // 'trace' => $e->getTraceAsString(), // يمكن إخفاء هذه التفاصيل في بيئة الإنتاج
                // 'file' => $e->getFile(), // يمكن إخفاء هذه التفاصيل في بيئة الإنتاج
                // 'line' => $e->getLine(), // يمكن إخفاء هذه التفاصيل في بيئة الإنتاج
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        if (!$authUser || !$authUser->hasAnyPermission(['products_create', 'company_owner', 'super_admin'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to create products.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $companyId = $authUser->company_id;
            $createdBy = $authUser->id;

            $validatedData = $request->validated();
            $validatedData['active'] = (bool) ($validatedData['active'] ?? false);
            $validatedData['featured'] = (bool) ($validatedData['featured'] ?? false);
            $validatedData['returnable'] = (bool) ($validatedData['returnable'] ?? false);
            // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
            $validatedData['company_id'] = ($authUser->hasPermissionTo('super_admin') && isset($validatedData['company_id']))
                ? $validatedData['company_id']
                : $companyId;
            $validatedData['created_by'] = $validatedData['created_by'] ?? $createdBy;
            $validatedData['slug'] = Product::generateSlug($validatedData['name']);

            $product = Product::create($validatedData);

            if ($request->has('variants') && is_array($request->input('variants'))) {
                foreach ($request->input('variants') as $variantData) {
                    $variantCreateData = collect($variantData)->except(['attributes', 'stocks'])->toArray();
                    $variantCreateData['company_id'] = $validatedData['company_id'];  // تأكد من ربطها بنفس شركة المنتج
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
                                'company_id' => $validatedData['company_id'],  // تأكد من ربطها بنفس شركة المنتج
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
                                'company_id' => $validatedData['company_id'],  // تأكد من ربطها بنفس شركة المنتج
                                'created_by' => $validatedData['created_by'],
                            ];
                            $variant->stocks()->create($stockCreateData);
                        }
                    }
                }
            }

            DB::commit();

            return ProductResource::make($product->load($this->relations));
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Product store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $authUser ? $authUser->id : null,
            ]);
            return response()->json([
                'message' => 'حدث خطأ أثناء حفظ المنتج.',
                // 'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        // تطبيق منطق الصلاحيات على المنتج المحدد
        // سنستخدم query جديدة هنا لتطبيق الـ scopes والتحقق من وجود المنتج
        $query = Product::where('id', $product->id)->with($this->relations);

        /** @var \App\Models\User $authUser */
        if ($authUser->hasAnyPermission(['products_show', 'products_all', 'super_admin'])) {
            // إذا كان لديه صلاحية 'products_show' أو 'products_all' أو 'super_admin'، لا حاجة لـ scope خاص
            // فقط نتأكد أن المنتج موجود
        } elseif ($authUser->hasPermissionTo('company_owner')) {
            $query->scopeCompany();
        } elseif ($authUser->hasPermissionTo('products_show_own')) {
            $query->scopeOwn();
        } elseif ($authUser->hasPermissionTo('products_show_self')) {
            $query->scopeSelf();
        } else {
            // إذا لم يكن لديه صلاحية رؤية هذا المنتج
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to view this product.'
            ], 403);
        }

        $authorizedProduct = $query->first();

        if (!$authorizedProduct) {
            // إذا لم يتم العثور على المنتج بعد تطبيق الـ scopes، يعني المستخدم غير مصرح له برؤيته
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Product not found or you do not have permission to view it.'
            ], 404);
        }

        return ProductResource::make($authorizedProduct);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        // تطبيق منطق الصلاحيات على المنتج المحدد قبل التحديث
        $query = Product::where('id', $product->id);
        /** @var \App\Models\User $authUser */
        if ($authUser->hasAnyPermission(['products_update', 'products_all', 'super_admin'])) {
            // إذا كان لديه صلاحية 'products_update' أو 'products_all' أو 'super_admin'، لا حاجة لـ scope خاص
            // فقط نتأكد أن المنتج موجود
        } elseif ($authUser->hasPermissionTo('company_owner')) {
            $query->scopeCompany();
        } elseif ($authUser->hasPermissionTo('products_update_own')) {
            $query->scopeOwn();
        } elseif ($authUser->hasPermissionTo('products_update_self')) {
            $query->scopeSelf();
        } else {
            // إذا لم يكن لديه صلاحية لتعديل هذا المنتج
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to update this product.'
            ], 403);
        }

        $authorizedProduct = $query->first();

        if (!$authorizedProduct) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Product not found or you do not have permission to update it.'
            ], 404);
        }

        // تأكد من أن الـ $product التي سنقوم بتحديثها هي نفسها التي تم التحقق من صلاحيتها
        $product = $authorizedProduct;

        DB::beginTransaction();
        try {
            $companyId = $authUser->company_id;
            $updatedBy = $authUser->id;

            $validatedData = $request->validated();
            $validatedData['active'] = (bool) ($validatedData['active'] ?? $product->active);  // احتفظ بالقيمة الحالية إذا لم ترسل
            $validatedData['featured'] = (bool) ($validatedData['featured'] ?? $product->featured);
            $validatedData['returnable'] = (bool) ($validatedData['returnable'] ?? $product->returnable);
            // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للمنتج.
            $validatedData['company_id'] = ($authUser->hasPermissionTo('super_admin') && isset($validatedData['company_id']))
                ? $validatedData['company_id']
                : $product->company_id;
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
                        'company_id' => $validatedData['company_id'],  // استخدام company_id للمنتج
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
                            'company_id' => $validatedData['company_id'],  // استخدام company_id للمنتج
                            'created_by' => $authUser->id,
                        ])
                        ->all();

                    $variant->attributes()->delete();
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
                                'company_id' => $validatedData['company_id'],  // استخدام company_id للمنتج
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
                        $variant->stocks()->delete();
                    }
                }
            } else {
                $product->variants()->delete();
            }

            DB::commit();

            return ProductResource::make($product->load($this->relations));
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Product update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $authUser ? $authUser->id : null,
            ]);
            return response()->json([
                'message' => 'حدث خطأ أثناء تحديث المنتج.',
                // 'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        // تطبيق منطق الصلاحيات على المنتج المحدد قبل الحذف
        $query = Product::where('id', $product->id);
        /** @var \App\Models\User $authUser */
        if ($authUser->hasAnyPermission(['products_delete', 'products_all', 'super_admin'])) {
            // إذا كان لديه صلاحية 'products_delete' أو 'products_all' أو 'super_admin'، لا حاجة لـ scope خاص
            // فقط نتأكد أن المنتج موجود
        } elseif ($authUser->hasPermissionTo('company_owner')) {
            $query->scopeCompany();
        } elseif ($authUser->hasPermissionTo('products_delete_own')) {
            $query->scopeOwn();
        } elseif ($authUser->hasPermissionTo('products_delete_self')) {
            $query->scopeSelf();
        } else {
            // إذا لم يكن لديه صلاحية لحذف هذا المنتج
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to delete this product.'
            ], 403);
        }

        $authorizedProduct = $query->first();

        if (!$authorizedProduct) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Product not found or you do not have permission to delete it.'
            ], 404);
        }

        // تأكد من أن الـ $product التي سنقوم بحذفها هي نفسها التي تم التحقق من صلاحيتها
        $product = $authorizedProduct;

        DB::beginTransaction();
        try {
            $product->variants()->delete();  // حذف المتغيرات المتعلقة، والتي بدورها ستحذف سجلات المخزون والخصائص
            $product->delete();

            DB::commit();
            return response()->json(null, 204);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Product deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $authUser ? $authUser->id : null,
            ]);
            return response()->json([
                'message' => 'حدث خطأ أثناء حذف المنتج.',
                // 'details' => $e->getMessage(),
            ], 500);
        }
    }
}

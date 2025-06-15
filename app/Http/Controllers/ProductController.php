<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
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

    public function index(Request $request)
    {
        $query = Product::with($this->relations);

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

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 10);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products)->additional([
            'total' => $products->total(),
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        DB::beginTransaction();
        try {
            $authUser = auth()->user();
            $companyId = $authUser->company_id;
            $createdBy = $authUser->id;

            $validatedData = $request->validated();
            $validatedData['active'] = (bool) ($validatedData['active'] ?? false);
            $validatedData['featured'] = (bool) ($validatedData['featured'] ?? false);
            $validatedData['returnable'] = (bool) ($validatedData['returnable'] ?? false);
            $validatedData['company_id'] = $validatedData['company_id'] ?? $companyId;
            $validatedData['created_by'] = $validatedData['created_by'] ?? $createdBy;
            $validatedData['slug'] = Product::generateSlug($validatedData['name']);

            $product = Product::create($validatedData);

            if ($request->has('variants') && is_array($request->input('variants'))) {
                foreach ($request->input('variants') as $variantData) {
                    $variantCreateData = collect($variantData)->except(['attributes', 'stocks'])->toArray();
                    $variantCreateData['company_id'] = $validatedData['company_id'];
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
                                'company_id' => $companyId,
                                'created_by' => $createdBy,
                            ]);
                        }
                    }

                    // التعامل مع مصفوفة المخزون (stocks)
                    if (!empty($variantData['stocks']) && is_array($variantData['stocks'])) {
                        foreach ($variantData['stocks'] as $stockData) {
                            $stockCreateData = [
                                'qty' => $stockData['qty'] ?? 0,
                                'reserved' => $stockData['reserved'] ?? 0,
                                'min_qty' => $stockData['min_qty'] ?? 0,
                                'cost' => $stockData['cost'] ?? null,
                                'batch' => $stockData['batch'] ?? null,
                                'expiry' => $stockData['expiry'] ?? null,
                                'loc' => $stockData['loc'] ?? null,
                                'status' => $stockData['status'] ?? 'available',
                                'warehouse_id' => $stockData['warehouse_id'] ?? null,
                                'company_id' => $companyId,
                                'created_by' => $createdBy,
                            ];
                            $variant->stocks()->create($stockCreateData);  // استخدم stocks() هنا
                        }
                    }
                }
            }

            DB::commit();

            return ProductResource::make($product->load($this->relations));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product store failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'حدث خطأ أثناء حفظ المنتج.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Product $product)
    {
        return ProductResource::make($product->load($this->relations));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        DB::beginTransaction();

        try {
            $authUser = auth()->user();
            $companyId = $authUser->company_id;
            $updatedBy = $authUser->id;  // استخدم updatedBy لتحديث السجلات

            $validatedData = $request->validated();
            $validatedData['active'] = (bool) ($validatedData['active'] ?? false);
            $validatedData['featured'] = (bool) ($validatedData['featured'] ?? false);
            $validatedData['returnable'] = (bool) ($validatedData['returnable'] ?? false);
            $productData = [
                'name' => $validatedData['name'],
                'slug' => $validatedData['slug'] ?? Product::generateSlug($validatedData['name']),
                'desc' => $validatedData['desc'] ?? null,
                'desc_long' => $validatedData['desc_long'] ?? null,
                'published_at' => $validatedData['published_at'] ?? null,
                'category_id' => $validatedData['category_id'],
                'brand_id' => $validatedData['brand_id'] ?? null,
                'company_id' => $validatedData['company_id'] ?? $companyId,
                'active' => $validatedData['active'] ?? true,  // تأكد من وجود هذه الحقول في الـ request
                'featured' => $validatedData['featured'] ?? false,
                'returnable' => $validatedData['returnable'] ?? true,
            ];

            $product->update($productData);

            // معالجة المتغيرات (Variants)
            $requestedVariantIds = collect($validatedData['variants'] ?? [])->pluck('id')->filter()->all();
            $product->variants()->whereNotIn('id', $requestedVariantIds)->delete();  // حذف المتغيرات غير الموجودة في الطلب

            if (!empty($validatedData['variants']) && is_array($validatedData['variants'])) {
                foreach ($validatedData['variants'] as $variantData) {
                    $variantCreateUpdateData = [
                        'barcode' => $variantData['barcode'] ?? null,
                        'sku' => $variantData['sku'] ?? null,
                        'retail_price' => $variantData['retail_price'] ?? null,
                        'wholesale_price' => $variantData['wholesale_price'] ?? null,
                        // 'profit_margin' => $variantData['profit_margin'] ?? null, // يتم حسابه عادة
                        'image' => $variantData['image'] ?? null,
                        'weight' => $variantData['weight'] ?? null,
                        'dimensions' => $variantData['dimensions'] ?? null,
                        'tax' => $variantData['tax'] ?? null,
                        'discount' => $variantData['discount'] ?? null,
                        'status' => $variantData['status'] ?? 'active',  // قيمة افتراضية
                        'company_id' => $variantData['company_id'] ?? $companyId,
                        'created_by' => $variantData['created_by'] ?? $authUser->id,
                        'product_id' => $product->id,
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
                            'company_id' => $companyId,
                            'created_by' => $authUser->id,
                        ])
                        ->all();

                    // حذف الخصائص القديمة للمتغير ثم إنشاء الجديدة.
                    // هذه الطريقة أبسط وتضمن مزامنة كاملة، ولكنها تحذف وتنشئ كل مرة.
                    // إذا كان الأداء حساساً جداً، يمكن استخدام syncWithPivotValues أو مقارنة يدوية.
                    $variant->attributes()->delete();
                    if (!empty($requestedAttributeIds)) {
                        $variant->attributes()->createMany($requestedAttributeIds);
                    }

                    // معالجة سجلات المخزون (Stocks)
                    $requestedStockIds = collect($variantData['stocks'] ?? [])->pluck('id')->filter()->all();
                    $variant->stocks()->whereNotIn('id', $requestedStockIds)->delete();  // حذف سجلات المخزون غير الموجودة في الطلب

                    if (!empty($variantData['stocks']) && is_array($variantData['stocks'])) {
                        foreach ($variantData['stocks'] as $stockData) {
                            $stockCreateUpdateData = [
                                'qty' => $stockData['qty'] ?? 0,
                                'reserved' => $stockData['reserved'] ?? 0,
                                'min_qty' => $stockData['min_qty'] ?? 0,
                                'cost' => $stockData['cost'] ?? null,
                                'batch' => $stockData['batch'] ?? null,
                                'expiry' => $stockData['expiry'] ?? null,
                                'loc' => $stockData['loc'] ?? null,
                                'status' => $stockData['status'] ?? 'available',
                                'warehouse_id' => $stockData['warehouse_id'] ?? null,
                                'company_id' => $stockData['company_id'] ?? $companyId,
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
                        // إذا لم يتم إرسال أي سجلات مخزون للمتغير، فقم بحذف جميع سجلات المخزون الحالية لهذا المتغير
                        $variant->stocks()->delete();
                    }
                }
            } else {
                // إذا لم يتم إرسال أي متغيرات، فقم بحذف جميع متغيرات المنتج (والتي ستحذف مخازنها وخصائصها تلقائيًا)
                $product->variants()->delete();
            }

            DB::commit();

            return ProductResource::make($product->load($this->relations));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'حدث خطأ أثناء تحديث المنتج.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Product $product)
    {
        DB::beginTransaction();
        try {
            // حذف المتغيرات المتعلقة، والتي بدورها ستحذف سجلات المخزون والخصائص
            $product->variants()->delete();
            $product->delete();

            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product deletion failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'حدث خطأ أثناء حذف المنتج.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}

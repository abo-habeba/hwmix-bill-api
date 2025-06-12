<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\error;

class ProductController extends Controller
{
    protected array $relations = [
        'company',
        'creator',
        'variants',
        // 'variants.product',
        'variants.attributes.attribute',
        'variants.attributes.attributeValue',
        'variants.stocks.warehouse',
    ];

    public function index(Request $request)
    {
        $query = Product::with($this->relations);
        // بحث نصي عام
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q
                    ->where('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%")
                    ->orWhere('slug', 'like', "%$search%")
                    ->orWhereHas('category', function ($q) use ($search) {
                        $q
                            ->where('name', 'like', "%$search%")
                            ->orWhere('description', 'like', "%$search%");
                    })
                    ->orWhereHas('brand', function ($q) use ($search) {
                        $q
                            ->where('name', 'like', "%$search%")
                            ->orWhere('description', 'like', "%$search%");
                    });
            });
        }

        // ترتيب
        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
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

            $validatedData = $request->validated();
            $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
            $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;

            $validatedData['slug'] = Product::generateSlug($validatedData['name']);

            $product = Product::create($validatedData);

            if ($request->has('variants')) {
                foreach ($request->input('variants') as $variantData) {
                    $variantData['warehouse_id'] = $request->input('warehouse_id');
                    $variantData['tax_rate'] ??= 0;

                    // تأكد من أن أسعار البيع موجودة في المتغير
                    unset($variantData['purchase_price']);

                    $variant = $product->variants()->create(
                        collect($variantData)->except(['attributes', 'stock'])->toArray()
                    );

                    // attributes
                    if (!empty($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $attributeData) {
                            if (empty($attributeData['attribute_id']) || empty($attributeData['attribute_value_id'])) {
                                continue;
                            }

                            $variant->attributes()->create([
                                'attribute_id' => $attributeData['attribute_id'],
                                'attribute_value_id' => $attributeData['attribute_value_id'],
                                'company_id' => $validatedData['company_id'],
                                'created_by' => $validatedData['created_by'],
                            ]);
                        }
                    }

                    // stock
                    if (!empty($variantData['stock'])) {
                        $variant->stock()->create([
                            'quantity' => $variantData['stock']['quantity'] ?? 0,
                            'expiry_date' => $variantData['stock']['expiry_date'] ?? null,
                            'status' => $variantData['stock']['status'] ?? 'available',
                            'purchase_price' => $variantData['stock']['purchase_price'] ?? 0,
                            'warehouse_id' => $request->input('warehouse_id'),
                            'company_id' => $validatedData['company_id'],
                            'created_by' => $validatedData['created_by'],
                        ]);
                    }
                }
            }

            DB::commit();

            return new ProductResource($product->load([
                'category',
                'category.parent',
                'brand',
                'warehouse',
                'variants.stock.warehouse',
                'variants.attributes.attribute',
                'variants.attributes.attributeValue',
            ]));
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'حدث خطأ أثناء الحفظ.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        DB::beginTransaction();

        try {
            $authUser = auth()->user();
            $validatedData = $request->validated();
            $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
            $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;

            // تحديث بيانات المنتج
            $product->update($validatedData);

            // تحديث slug إذا الاسم اتغير
            if ($request->has('name')) {
                $slug = Product::generateSlug($request->input('name'));
                $product->update(['slug' => $slug]);
            }

            if ($request->has('variants')) {
                $variantIds = [];

                foreach ($request->input('variants') as $variantData) {
                    $variantId = $variantData['id'] ?? null;
                    $variantData['warehouse_id'] = $request->input('warehouse_id');
                    $variantData['tax_rate'] = $variantData['tax_rate'] ?? 0;

                    // حذف purchase_price من المتغير
                    unset($variantData['purchase_price']);

                    // إنشاء أو تحديث المتغير
                    if ($variantId) {
                        $variant = $product->variants()->find($variantId);
                        if ($variant) {
                            $variant->update(collect($variantData)->except(['id', 'attributes', 'stock'])->toArray());
                        } else {
                            $variant = $product->variants()->create(collect($variantData)->except(['id', 'attributes', 'stock'])->toArray());
                        }
                    } else {
                        $variant = $product->variants()->create(collect($variantData)->except(['id', 'attributes', 'stock'])->toArray());
                    }

                    $variantIds[] = $variant->id;

                    // الخصائص
                    if (isset($variantData['attributes'])) {
                        $attrIds = [];

                        foreach ($variantData['attributes'] as $attrData) {
                            if (empty($attrData['attribute_id']) || empty($attrData['attribute_value_id'])) {
                                continue;
                            }

                            $attr = $variant
                                ->attributes()
                                ->where('attribute_id', $attrData['attribute_id'])
                                ->first();

                            if ($attr) {
                                $attr->update([
                                    'attribute_value_id' => $attrData['attribute_value_id'],
                                    'company_id' => $validatedData['company_id'],
                                    'created_by' => $validatedData['created_by'],
                                ]);
                            } else {
                                $attr = $variant->attributes()->create([
                                    'attribute_id' => $attrData['attribute_id'],
                                    'attribute_value_id' => $attrData['attribute_value_id'],
                                    'company_id' => $validatedData['company_id'],
                                    'created_by' => $validatedData['created_by'],
                                ]);
                            }

                            $attrIds[] = $attr->id;
                        }

                        // حذف الخصائص غير المرسلة
                        $variant->attributes()->whereNotIn('id', $attrIds)->delete();
                    }

                    // تحديث المخزون
                    if (!empty($variantData['stock'])) {
                        $stock = $variant->stock;

                        $stockData = [
                            'quantity' => $variantData['stock']['quantity'] ?? 0,
                            'expiry_date' => $variantData['stock']['expiry_date'] ?? null,
                            'status' => $variantData['stock']['status'] ?? 'available',
                            'purchase_price' => $variantData['stock']['purchase_price'] ?? 0,
                            'warehouse_id' => $request->input('warehouse_id'),
                            'company_id' => $validatedData['company_id'],
                            'created_by' => $validatedData['created_by'],
                        ];

                        if ($stock) {
                            $stock->update($stockData);
                        } else {
                            $variant->stock()->create($stockData);
                        }
                    }
                }

                // حذف المتغيرات غير المرسلة
                $product->variants()->whereNotIn('id', $variantIds)->delete();
            }

            DB::commit();

            return new ProductResource($product->load([
                'category',
                'category.parent',
                'brand',
                'warehouse',
                'variants.stock.warehouse',
                'variants.attributes.attribute',
                'variants.attributes.attributeValue',
            ]));
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'حدث خطأ أثناء التحديث.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function search(Request $request)
    {
        $query = Product::query();

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        $products = $query->with([
            'category',
            'category.parent',
            'brand',
            'warehouse',
            'variants.stock.warehouse',
            'variants.attributes.attribute',
            'variants.attributes.attributeValue',
        ])->get();

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $product->load([
            'category',
            'category.parent',
            'brand',
            'warehouse',
            'variants.stock.warehouse',
            'variants.attributes.attribute',
            'variants.attributes.attributeValue',
        ]);
        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}

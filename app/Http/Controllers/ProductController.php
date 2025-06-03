<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\Product\ProductResource;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use Illuminate\Http\Request;
use function Laravel\Prompts\error;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // $query = Product::with(['category', 'brand', 'variants.attributes.attributeValue']);
        $query = Product::with(relations: [
            'category',
            'category.parent',
            'brand',
            'warehouse',
            'variants.stock.warehouse',
            'variants.attributes.attribute',
            'variants.attributes.attributeValue',
        ]);
        // بحث نصي عام
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%")
                    ->orWhere('slug', 'like', "%$search%")
                    ->orWhereHas('category', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%")
                            ->orWhere('description', 'like', "%$search%");
                    })
                    ->orWhereHas('brand', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%")
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
            // استرجاع بيانات المستخدم المُصادق عليه
            $authUser = auth()->user();

            // التحقق من البيانات المدخلة
            $validatedData = $request->validated();
            $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
            $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;

            // توليد الـ slug قبل الإنشاء
            $validatedData['slug'] = Product::generateSlug($request->input('name'));
            // إنشاء المنتج بكل البيانات مرة واحدة
            $product = Product::create($validatedData);

            // التحقق من وجود متغيرات
            if ($request->has('variants')) {
                foreach ($request->input('variants') as $variantData) {
                    $variantData['warehouse_id'] = $request->input('warehouse_id');
                    $variantData['tax_rate'] ??= 0;

                    // إنشاء متغير المنتج
                    $variant = $product->variants()->create(
                        collect($variantData)->except(['attributes', 'stock'])->toArray()
                    );

                    // إضافة الخصائص المرتبطة بالمتغير
                    if (!empty($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $attributeData) {
                            if (empty($attributeData['attribute_id']) || empty($attributeData['attribute_value_id'])) {
                                continue; // تخطي إذا كان أحد الحقلين غير موجود أو null
                            }
                            $variant->attributes()->create([
                                'attribute_id' => $attributeData['attribute_id'],
                                'attribute_value_id' => $attributeData['attribute_value_id'],
                                'company_id' => $validatedData['company_id'] ?? $authUser->company_id,
                                'created_by' => $validatedData['created_by'] ?? $authUser->id,
                            ]);
                        }
                    }

                    // إضافة المخزون المرتبط بالمتغير
                    if (!empty($variantData['stock'])) {
                        $variant->stock()->create([
                            'quantity' => $variantData['stock']['quantity'] ?? 0,
                            'expiry_date' => $variantData['stock']['expiry_date'] ?? null,
                            'status' => $variantData['stock']['status'] ?? 'available',
                            'warehouse_id' => $request->input('warehouse_id'),
                            'company_id' => $validatedData['company_id'] ?? $authUser->company_id,
                            'created_by' => $validatedData['created_by'] ?? $authUser->id,
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
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
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

            // تحديث slug إذا تغير الاسم
            if ($request->has('name')) {
                $slug = Product::generateSlug($request->input('name'));
                $product->update(['slug' => $slug]);
            }

            // تحديث المتغيرات (variants)
            if ($request->has('variants')) {
                $variantIds = [];

                foreach ($request->input('variants') as $variantData) {
                    $variantId = $variantData['id'] ?? null;
                    $variantData['warehouse_id'] = $request->input('warehouse_id');
                    $variantData['tax_rate'] = $variantData['tax_rate'] ?? 0;

                    // تحديث أو إنشاء المتغير
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

                    // تحديث أو إنشاء الخصائص (attributes) للمتغير
                    if (isset($variantData['attributes'])) {
                        $attrIds = [];

                        foreach ($variantData['attributes'] as $attrData) {
                            if (empty($attrData['attribute_id']) || empty($attrData['attribute_value_id'])) {
                                continue;
                            }

                            // ابحث عن السطر حسب attribute_id
                            $attr = $variant->attributes()
                                ->where('attribute_id', $attrData['attribute_id'])
                                ->first();

                            if ($attr) {
                                $attr->update([
                                    'attribute_value_id' => $attrData['attribute_value_id'],
                                    'company_id' => $validatedData['company_id'] ?? $authUser->company_id,
                                    'created_by' => $validatedData['created_by'] ?? $authUser->id,
                                ]);
                            } else {
                                $attr = $variant->attributes()->create([
                                    'attribute_id' => $attrData['attribute_id'],
                                    'attribute_value_id' => $attrData['attribute_value_id'],
                                    'company_id' => $validatedData['company_id'] ?? $authUser->company_id,
                                    'created_by' => $validatedData['created_by'] ?? $authUser->id,
                                ]);
                            }

                            $attrIds[] = $attr->id;
                        }

                        // حذف الخصائص غير المرسلة
                        $variant->attributes()->whereNotIn('id', $attrIds)->delete();
                    }

                    // تحديث أو إنشاء المخزون المرتبط بالمتغير (لو موجود)
                    if (!empty($variantData['stock'])) {
                        $stock = $variant->stock;
                        $stockData = [
                            'quantity' => $variantData['stock']['quantity'] ?? 0,
                            'expiry_date' => $variantData['stock']['expiry_date'] ?? null,
                            'status' => $variantData['stock']['status'] ?? 'available',
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
                'trace' => $e->getTraceAsString(),
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

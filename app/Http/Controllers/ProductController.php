<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\Product\ProductResource;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'brand', 'variants.attributes'])->get();
        return ProductResource::collection($products);
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

            // إنشاء المنتج
            $product = Product::create($validatedData);

            // إنشاء الـ slug للمنتج
            $slug = Product::generateSlug($request->name);
            $product->update(['slug' => $slug]);

            // التحقق من وجود متغيرات
            if ($request->has('variants')) {
                foreach ($request->variants as $variantData) {
                    $variantData['warehouse_id'] = $request->warehouse_id;

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
                            ]);
                        }
                    }
                    // إضافة المخزون المرتبط بالمتغير
                    if (!empty($variantData['stock'])) {
                        $variant->stock()->create([
                            'quantity' => $variantData['stock']['quantity'] ?? 0,
                            'expiry_date' => $variantData['stock']['expiry_date'] ?? null,
                            'status' => $variantData['stock']['status'] ?? 'available',
                            'warehouse_id' => $request->warehouse_id,
                            'company_id' => $validatedData['company_id'] ?? $authUser->company_id,
                            'created_by' => $validatedData['created_by'] ?? $authUser->id,
                        ]);
                    }
                }
            }
            DB::commit();
            return new ProductResource($product->load(['variants.stock', 'variants.attributes.values']));
        } catch (\Exception $e) {
            // في حالة حدوث أي خطأ، نقوم بالتراجع عن التغييرات
            DB::rollBack();

            // إرجاع خطأ مع تفاصيل المشكلة
            return response()->json([
                'error' => 'حدث خطأ أثناء الحفظ.',
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

        $products = $query->with(['category', 'brand'])->get();

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $product->load(['category', 'brand']);
        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());
        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}

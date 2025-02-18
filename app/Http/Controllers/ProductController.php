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
        $products = Product::with(['category', 'brand'])->get();
        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        DB::beginTransaction();

        try {
            // إنشاء المنتج
            $product = Product::create($request->validated());

            // إضافة المتغيرات الخاصة بالمنتج
            if ($request->has('variants')) {
                foreach ($request->variants as $variantData) {
                    $variant = $product->variants()->create($variantData);

                    // إضافة خصائص المتغيرات
                    if (!empty($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $attribute) {
                            $variant->attributes()->create([
                                'attribute_id' => $attribute['attribute_id'],
                                'attribute_value_id' => $attribute['attribute_value_id'],
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return new ProductResource($product);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'حدث خطأ أثناء الحفظ.'], 500);
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

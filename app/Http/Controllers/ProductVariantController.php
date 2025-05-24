<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\ProductVariant\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariant\ProductVariantResource;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function index()
    {
        $productVariants = ProductVariant::with('product', 'warehouse', 'stock', 'attributes.attribute', 'attributes.attributeValue')->get();
        return ProductVariantResource::collection($productVariants);
    }

    public function store(StoreProductVariantRequest $request)
    {
        $validated = $request->validated();
        $productVariant = ProductVariant::create($validated);
        return new ProductVariantResource($productVariant);
    }

    public function show(string $id)
    {
        $productVariant = ProductVariant::findOrFail($id);
        return new ProductVariantResource($productVariant);
    }

    public function update(UpdateProductVariantRequest $request, string $id)
    {
        $validated = $request->validated();
        $productVariant = ProductVariant::findOrFail($id);
        $productVariant->update($validated);
        return new ProductVariantResource($productVariant);
    }

    public function destroy(string $id)
    {
        $productVariant = ProductVariant::findOrFail($id);
        $productVariant->delete();
        return response()->json(null, 204);
    }

    /**
     * البحث عن متغيرات منتج باستخدام براميتر بحث
     */
    public function searchByProduct(Request $request)
    {
        $query = \App\Models\Product::query();
        $search = $request->get('search');
        if (empty($search) || mb_strlen($search) <= 2) {
            return ProductVariantResource::collection(collect([]));
        }
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%$search%")
              ->orWhere('description', 'like', "%$search%");
        });
        $perPage = max(1, (int) $request->get('per_page', 10));
        $products = $query->with(['variants.product', 'variants.warehouse', 'variants.stock', 'variants.attributes.attribute', 'variants.attributes.attributeValue'])->paginate($perPage);
        $variants = $products->getCollection()->flatMap(function($product) {
            return $product->variants;
        });
        // إعادة النتائج مع معلومات الباجينيشن
        return response()->json([
            'data' => ProductVariantResource::collection($variants),
            'total' => $products->total(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
        ]);
    }
}

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

        // حفظ الخصائص (attributes)
        if ($request->has('attributes')) {
            foreach ($request->input('attributes') as $attr) {
                if (!empty($attr['attribute_id']) && !empty($attr['attribute_value_id'])) {
                    $productVariant->attributes()->create([
                        'attribute_id' => $attr['attribute_id'],
                        'attribute_value_id' => $attr['attribute_value_id'],
                    ]);
                }
            }
        }

        // حفظ المخزون (stock)
        if ($request->has('stock') && is_array($request->input('stock'))) {
            $productVariant->stock()->create($request->input('stock'));
        }

        return new ProductVariantResource($productVariant->load([
            'product', 'warehouse', 'stock', 'attributes.attribute', 'attributes.attributeValue'
        ]));
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

        // تحديث الخصائص (attributes)
        if ($request->has('attributes')) {
            $attrIds = [];
            foreach ($request->input('attributes') as $attr) {
                if (!empty($attr['id'])) {
                    $attribute = $productVariant->attributes()->find($attr['id']);
                    if ($attribute) {
                        $attribute->update([
                            'attribute_id' => $attr['attribute_id'],
                            'attribute_value_id' => $attr['attribute_value_id'],
                        ]);
                        $attrIds[] = $attribute->id;
                        continue;
                    }
                }
                // إذا لم يوجد id أو لم يوجد السطر، أنشئ جديد
                $newAttr = $productVariant->attributes()->create([
                    'attribute_id' => $attr['attribute_id'],
                    'attribute_value_id' => $attr['attribute_value_id'],
                ]);
                $attrIds[] = $newAttr->id;
            }
            // حذف الخصائص غير المرسلة
            $productVariant->attributes()->whereNotIn('id', $attrIds)->delete();
        }

        // تحديث أو إنشاء المخزون (stock)
        if ($request->has('stock') && is_array($request->input('stock'))) {
            if ($productVariant->stock) {
                $productVariant->stock->update($request->input('stock'));
            } else {
                $productVariant->stock()->create($request->input('stock'));
            }
        }

        return new ProductVariantResource($productVariant->load([
            'product', 'warehouse', 'stock', 'attributes.attribute', 'attributes.attributeValue'
        ]));
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

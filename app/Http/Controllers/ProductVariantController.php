<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\ProductVariant\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariant\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    protected $relations = [
        'creator',
        'company.users',
        'company.userCompanyCash',
        'company.images',
        'stocks.variant',
        'stocks.warehouse',
        'stocks.company',
        'product.creator',
        'product.company',
        'product.category',
        'product.brand',
        'attributes.variant',
        'attributes.attribute',
        'attributes.attributeValue',
    ];

    public function index(Request $request)
    {
        $query = ProductVariant::with($this->relations);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q
                    ->where('sku', 'like', "%$search%")
                    ->orWhereHas('product', function ($q) use ($search) {
                        $q->where('name', 'like', "%$search%");
                    });
            });
        }

        return $query->paginate();
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

        return new ProductVariantResource($productVariant->load($this->relations));
    }

    public function show($id)
    {
        return ProductVariant::with($this->relations)->findOrFail($id);
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

        return new ProductVariantResource($productVariant->load($this->relations));
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
        $query =
            Product::query();
        $search = $request->get('search');
        if (empty($search) || mb_strlen($search) <= 2) {
            return ProductVariantResource::collection(collect([]));
        }
        $query->where(function ($q) use ($search) {
            $q
                ->where('name', 'like', "%$search%")
                ->orWhere('desc', 'like', "%$search%");
        });
        $perPage = max(1, (int) $request->get('per_page', 10));
        $products = $query->with(['variants' => function ($query) {
            $query->with($this->relations);
        }])->paginate($perPage);
        $variants = collect($products->items())->flatMap(function ($product) {
            return $product->variants;
        });

        return ProductVariantResource::collection($variants);
    }
}

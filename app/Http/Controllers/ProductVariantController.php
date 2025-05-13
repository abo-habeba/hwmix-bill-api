<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductVariant\StoreProductVariantRequest;
use App\Http\Requests\ProductVariant\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariant\ProductVariantResource;
use App\Models\ProductVariant;

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
}

<?php

namespace App\Http\Resources\ProductVariant;

use Illuminate\Http\Request;
use App\Http\Resources\Product\ProductResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Warehouse\WarehouseResource;
use App\Http\Resources\ProductVariantAttribute\ProductVariantAttributeResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->product->name ?? null,
            'description' => $this->product->description ?? null,
            'category' => $this->product->category->name ?? null,
            'brand' => $this->product->brand->name ?? null,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'purchase_price' => $this->purchase_price,
            'wholesale_price' => $this->wholesale_price,
            'retail_price' => $this->retail_price,
            'stock_threshold' => $this->stock_threshold,
            'status' => $this->status,
            'expiry_date' => $this->expiry_date,
            'image_url' => $this->image_url,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'tax_rate' => $this->tax_rate,
            'discount' => $this->discount,
            'stock_quantity' => $this->whenLoaded('stock') ? $this->stock->quantity : 0,
            // 'product' => new ProductResource($this->whenLoaded('product')),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'attributes' => ProductVariantAttributeResource::collection($this->whenLoaded('attributes')),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}

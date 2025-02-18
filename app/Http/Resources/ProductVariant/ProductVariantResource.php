<?php

namespace App\Http\Resources\ProductVariant;

use Illuminate\Http\Request;
use App\Http\Resources\Product\ProductResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Warehouse\WarehouseResource;

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
            'product' => new ProductResource($this->product),
            'warehouse' => new WarehouseResource($this->warehouse),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

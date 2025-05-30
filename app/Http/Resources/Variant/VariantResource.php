<?php

namespace App\Http\Resources\Variant;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductVariantAttribute\ProductVariantAttributeResource;

class VariantResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'barcode'         => $this->barcode,
            'sku'             => $this->sku,
            'purchase_price'  => $this->purchase_price,
            'wholesale_price' => $this->wholesale_price,
            'retail_price'    => $this->retail_price,
            'stock_threshold' => $this->stock_threshold,
            'status'          => $this->status,
            'expiry_date'     => $this->expiry_date,
            'image_url'       => $this->image_url,
            'weight'          => $this->weight,
            'dimensions'      => $this->dimensions,
            'tax_rate'        => $this->tax_rate,
            'discount'        => $this->discount,
            'product_id'      => $this->product_id,
            'created_at'      => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'      => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'attributes'      => ProductVariantAttributeResource::collection($this->whenLoaded('attributes')),
        ];
    }
}

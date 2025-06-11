<?php

namespace App\Http\Resources\Product;

use App\Http\Resources\Brand\BrandResource;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Company\CompaniesResource;
use App\Http\Resources\ProductVariant\ProductVariantResource;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\Variant\VariantResource;
use App\Http\Resources\Warehouse\WarehouseResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class ProductResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'retail_price' => $this->retail_price,
            'wholesale_price' => $this->wholesale_price,
            'profit_margin' => $this->profit_margin,
            'image' => $this->image,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'status' => $this->status,
            'product' => new ProductResource($this->whenLoaded('product')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'attributes' => ProductVariantAttributeResource::collection($this->whenLoaded('attributes')),
            'stocks' => StockResource::collection($this->whenLoaded('stocks')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

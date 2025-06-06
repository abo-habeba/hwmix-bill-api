<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\Brand\BrandResource;
use App\Http\Resources\Variant\VariantResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Company\CompaniesResource;
use App\Http\Resources\Warehouse\WarehouseResource;
use App\Http\Resources\ProductVariant\ProductVariantResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => (bool) $this->is_active,
            'featured' => (bool) $this->featured,
            'is_returnable' => (bool) $this->is_returnable,
            'meta_data' => json_decode($this->meta_data),
            'published_at' => $this->published_at,
            'description' => $this->description,
            'warehouse_id' => $this->warehouse_id,
            'description_long' => $this->description_long,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            // 'variants' => VariantResource::collection($this->whenLoaded('variants')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            // 'company' => new CompaniesResource($this->whenLoaded('company')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}


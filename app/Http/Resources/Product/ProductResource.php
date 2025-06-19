<?php

namespace App\Http\Resources\Product;

use App\Http\Resources\Brand\BrandResource;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Company\CompanyResource;
use App\Http\Resources\Company\CompanyResource;
use App\Http\Resources\ProductVariant\ProductVariantResource;
use App\Http\Resources\ProductVariantAttribute\ProductVariantAttributeResource;
use App\Http\Resources\Stock\StockResource;
use App\Http\Resources\User\UserBasicResource;
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
            'name' => $this->name,
            'slug' => $this->slug,
            'active' => (bool) $this->active,
            'featured' => (bool) $this->featured,
            'returnable' => (bool) $this->returnable,
            'desc' => $this->desc,  // تم إرجاع الاسم إلى 'desc'
            'desc_long' => $this->desc_long,  // تم إرجاع الاسم إلى 'desc_long'
            'category_id' => $this->category_id,
            'brand_id' => $this->whenNotNull($this->brand_id),
            'company_id' => $this->company_id,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'published_at' => $this->whenNotNull($this->published_at ? $this->published_at->format('Y-m-d H:i:s') : null),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

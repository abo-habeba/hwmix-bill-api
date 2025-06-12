<?php

namespace App\Http\Resources\ProductVariant;

use App\Http\Resources\Company\CompanyResource;
use App\Http\Resources\Product\ProductResource;
use App\Http\Resources\ProductVariantAttribute\ProductVariantAttributeResource;
use App\Http\Resources\Stock\StockResource;
use App\Http\Resources\User\UserBasicResource;
use App\Http\Resources\Warehouse\WarehouseResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

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
            // بيانات متغير المنتج
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'retail_price' => $this->retail_price,
            'wholesale_price' => $this->wholesale_price,
            'image' => $this->image,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'tax' => $this->tax,
            'discount' => $this->discount,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            // ✅ دي الحقول الخاصة بالمنتج، هنرجعها فقط لو العلاقة محمّلة
            $this->mergeWhen($this->relationLoaded('product'), [
                'product_name' => $this->product->name,
                'product_slug' => $this->product->slug,
                'product_active' => (bool) $this->product->active,
                'product_featured' => (bool) $this->product->featured,
                'product_returnable' => (bool) $this->product->returnable,
                'product_desc' => $this->product->desc,
                'product_desc_long' => $this->product->desc_long,
                'product_published_at' => $this->product->published_at?->format('Y-m-d H:i:s'),
                'category_id' => $this->product->category_id,
                'brand_id' => $this->product->brand_id,
                'company_id' => $this->product->company_id,
            ]),
            // العلاقة  لو محمّلة
            'attributes' => ProductVariantAttributeResource::collection($this->whenLoaded('attributes')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'stock' => new StockResource($this->whenLoaded('stock')),
        ];
    }
}

<?php

namespace App\Http\Resources\ProductVariant;

use Illuminate\Http\Request;
use App\Http\Resources\Brand\BrandResource;
use App\Http\Resources\Stock\StockResource;
use App\Http\Resources\User\UserBasicResource;
use App\Http\Resources\Company\CompanyResource;
use App\Http\Resources\Product\ProductResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Warehouse\WarehouseResource;
use App\Http\Resources\ProductVariantAttribute\ProductVariantAttributeResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availableStocks = $this->stocks->where('status', 'available');

        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'retail_price' => $this->retail_price,
            'wholesale_price' => $this->wholesale_price,
            'image' => $this->image,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'tax' => $this->tax,
            'cost' => $availableStocks->sortByDesc('created_at')->first()?->cost ?? 0,
            'quantity' => $availableStocks->sum('quantity') ?? null,
            'min_quantity' => $this->min_quantity,
            'discount' => $this->discount,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // ✅ علاقات المنتج الأساسية
            $this->mergeWhen($this->relationLoaded('product'), [
                'product_id' => $this->product->id,
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

            // ✅ الخصائص (attributes) + القيم المرتبطة
            'attributes' => ProductVariantAttributeResource::collection($this->whenLoaded('attributes')),

            // ✅ الخزن
            'stocks' => StockResource::collection($this->whenLoaded('stocks')),

            // ✅ منشئ المتغير
            'creator' => new UserBasicResource($this->whenLoaded('creator')),

            // ✅ الشركة التابعة للمتغير
            'company' => new CompanyResource($this->whenLoaded('company')),
        ];
    }
}

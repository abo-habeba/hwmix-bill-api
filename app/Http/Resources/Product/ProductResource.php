<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\Brand\BrandResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Company\CompaniesResource;
use App\Http\Resources\Warehouse\WarehouseResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'featured' => $this->featured,
            'is_returnable' => $this->is_returnable,
            'meta_data' => json_decode($this->meta_data),
            'published_at' => $this->published_at,
            'description' => $this->description,
            'description_long' => $this->description_long,
            'company' => new CompaniesResource($this->company),
            'created_by' => new UserResource($this->createdBy),
            'category' => new CategoryResource($this->category),
            'brand' => new BrandResource($this->brand),
            'warehouse' => new WarehouseResource($this->warehouse),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}


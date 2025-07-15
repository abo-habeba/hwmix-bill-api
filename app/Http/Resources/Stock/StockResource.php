<?php

namespace App\Http\Resources\Stock;

use App\Http\Resources\Company\CompanyResource;
use App\Http\Resources\ProductVariant\ProductVariantResource;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\Warehouse\WarehouseResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class StockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // dd($this->quantity);
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'reserved' => $this->reserved,
            'min_quantity' => $this->min_quantity,
            'cost' => $this->cost,
            'batch' => $this->batch,
            'expiry' => $this->expiry,
            'loc' => $this->loc,
            'status' => $this->status,
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'updater' => new UserResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

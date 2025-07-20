<?php

namespace App\Http\Resources\ProductVariantAttribute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Attribute\AttributeResource;
use App\Http\Resources\AttributeValue\AttributeValueResource;

class ProductVariantAttributeResource extends JsonResource
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
            'attribute_id' => $this->attribute_id,
            'attribute_value_id' => $this->attribute_value_id,
            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
            'attribute_value' => new AttributeValueResource($this->whenLoaded('attributeValue')),
        ];
    }
}

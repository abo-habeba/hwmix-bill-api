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
            'attribute_id' => $this->id,
            'attribute_value_id' => $this->attributeValue->id,
            'name' => $this->attribute->name ?? null,  // استخراج اسم الـ attribute مباشرة
            'value' => $this->attributeValue ?? null,  // استخراج قيمة الـ attribute_value مباشرة
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}

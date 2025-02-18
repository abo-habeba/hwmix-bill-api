<?php

namespace App\Http\Resources\Attribute;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\AttributeValue\AttributeValueResource;

class AttributeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'values' => AttributeValueResource::collection($this->values),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'id' => $this->id,
        ];
    }
}

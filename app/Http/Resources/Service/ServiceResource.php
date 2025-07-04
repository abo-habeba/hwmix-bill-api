<?php

namespace App\Http\Resources\Service;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'default_price' => $this->default_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            // علاقات
            'company' => $this->whenLoaded('company'),
            'creator' => $this->whenLoaded('creator'),
        ];
    }
}

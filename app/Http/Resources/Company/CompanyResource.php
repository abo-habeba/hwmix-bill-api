<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class CompanyResource extends JsonResource
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
            'owner_name' => $this->owner_name,
            'name' => $this->name,
            'field' => $this->field,
            'phone' => $this->phone,
            'address' => $this->address,
            'description' => $this->description,
            'email' => $this->email,
            'created_by' => $this->created_by,
            'logo' => $this->logo ? asset($this->logo->url) : null,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}

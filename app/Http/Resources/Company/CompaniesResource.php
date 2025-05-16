<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompaniesResource extends JsonResource
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
            'company_id' => $this->company_id,
            'logo' => $this->logo ? asset('storage/' . $this->logo->url) : null,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}

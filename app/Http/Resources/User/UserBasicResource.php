<?php

namespace App\Http\Resources\User;

use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class UserBasicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        // $this->load('companies');
        $company = $this->companies?->firstWhere('id', $this->company_id);
        $logo = $company?->logo;
        $logoUrl = $logo && $logo->url ? asset('storage/' . $logo->url) : null;
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'nickname' => $this->nickname,
            'phone' => $this->phone,
            'customer_type' => $this->customer_type,
        ];
    }
}

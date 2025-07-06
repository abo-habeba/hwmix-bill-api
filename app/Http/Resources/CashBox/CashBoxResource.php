<?php

namespace App\Http\Resources\CashBox;

use Illuminate\Http\Request;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CashBoxResource extends JsonResource
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
            'name' => $this->name,
            'balance' => $this->balance,
            'cash_type' => $this->typeBox->name,
            'cash_box_type_id' => $this->cash_box_type_id,
            'user_id' => $this->user_id,
            'created_by' => $this->created_by,
            'company_id' => $this->company_id,
            'is_default' => $this->is_default,
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
        ];
    }
}

<?php

namespace App\Http\Resources;

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
            'user' => new UserResource($this->whenLoaded('user')),
            'company' => new CompanyResource($this->whenLoaded('company')),
        ];
    }
}

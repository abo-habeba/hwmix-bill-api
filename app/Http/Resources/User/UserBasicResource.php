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
        $avatarImage = $this->images->where('type', 'avatar')->first();
        return [
            'id' => $this->id,
            'balance' => optional($this->cashBoxDefault)->balance ?? 0,
            'full_name' => $this->full_name,
            'nickname' => $this->nickname,
            'phone' => $this->phone,
            'customer_type' => $this->customer_type,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'cash_box_id' => optional($this->cashBoxDefault)->id,
            'avatar_url' => $avatarImage ? asset($avatarImage->url) : null,
            'status' => $this->status,
            'company_id' => $this->company_id,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RevenueResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'customer_id' => $this->customer_id,
            'created_by' => $this->created_by,
            'wallet_id' => $this->wallet_id,
            'company_id' => $this->company_id,
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'payment_method' => $this->payment_method,
            'note' => $this->note,
            'revenue_date' => $this->revenue_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

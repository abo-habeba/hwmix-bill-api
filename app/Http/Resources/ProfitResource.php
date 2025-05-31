<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfitResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'created_by' => $this->created_by,
            'customer_id' => $this->customer_id,
            'company_id' => $this->company_id,
            'revenue_amount' => $this->revenue_amount,
            'cost_amount' => $this->cost_amount,
            'profit_amount' => $this->profit_amount,
            'note' => $this->note,
            'profit_date' => $this->profit_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

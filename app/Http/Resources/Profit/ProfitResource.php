<?php

namespace App\Http\Resources\Profit;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'created_by' => $this->created_by,
            'user_id' => $this->user_id,
            'company_id' => $this->company_id,
            'revenue_amount' => $this->revenue_amount,
            'cost_amount' => $this->cost_amount,
            'profit_amount' => $this->profit_amount,
            'note' => $this->note,
            'profit_date' => $this->profit_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // علاقات
            'company' => $this->whenLoaded('company'),
            'creator' => $this->whenLoaded('creator'),
        ];
    }
}

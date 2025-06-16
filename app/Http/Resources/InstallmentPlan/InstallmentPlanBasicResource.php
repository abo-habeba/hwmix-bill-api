<?php

namespace App\Http\Resources\InstallmentPlan;

use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPlanBasicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'installment_count' => $this->installment_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'invoice_id' => $this->invoice_id ?? null,
            'user_id' => $this->user_id ?? null,
            'down_payment' => $this->down_payment ?? 0,
            'remaining_amount' => $this->remaining_amount ?? 0,
            'number_of_installments' => $this->number_of_installments ?? 0,
            'installment_amount' => $this->installment_amount ?? 0,
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'status' => $this->status ?? 'pending',
            'notes' => $this->notes ?? '',
        ];
    }
}

<?php

namespace App\Http\Resources\InstallmentPayment;

use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPaymentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'installment_plan_id' => $this->installment_plan_id,
            'payment_date' => $this->payment_date,
            'amount_paid' => $this->amount_paid,
            'excess_amount' => $this->excess_amount ? (float) $this->excess_amount : false,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

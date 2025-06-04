<?php

namespace App\Http\Resources\InstallmentPaymentDetail;

use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPaymentDetailResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'installment_payment_id' => $this->installment_payment_id,
            'installment_id' => $this->installment_id,
            'amount_paid' => $this->amount_paid,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

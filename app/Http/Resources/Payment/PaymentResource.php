<?php
namespace App\Http\Resources\Payment;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'payment_date' => $this->payment_date,
            'amount' => $this->amount,
            'method' => $this->method,
            'notes' => $this->notes,
            'is_split' => $this->is_split,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'installments' => PaymentInstallmentResource::collection($this->whenLoaded('installments')),
            'payment_method' => $this->whenLoaded('paymentMethod'),
        ];
    }
}

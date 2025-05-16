<?php
namespace App\Http\Resources\Payment;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentInstallmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'allocated_amount' => $this->pivot->allocated_amount ?? null,
            'installment_id' => $this->id,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d H:i:s') : null,
            'amount' => $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at ? $this->paid_at->format('Y-m-d H:i:s') : null,
            'remaining' => $this->remaining,
        ];
    }
}

<?php
namespace App\Http\Resources\Installment;

use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'installment_plan_id' => $this->installment_plan_id,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d H:i:s') : null,
            'amount' => $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at ? $this->paid_at->format('Y-m-d H:i:s') : null,
            'remaining' => $this->remaining,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'payments' => PaymentInstallmentResource::collection($this->whenLoaded('payments')),
        ];
    }
}

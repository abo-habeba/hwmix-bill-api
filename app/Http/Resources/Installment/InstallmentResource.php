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
            'amount' => $this->amount,
            'status' => $this->status,
            'remaining' => $this->remaining,
            'due_date' => \Carbon\Carbon::parse($this->due_date)->format('Y-m-d'),
            'paid_at' => \Carbon\Carbon::parse($this->paid_at)->format('Y-m-d'),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            // 'payments' => PaymentInstallmentResource::collection($this->whenLoaded('payments')),
        ];
    }
}

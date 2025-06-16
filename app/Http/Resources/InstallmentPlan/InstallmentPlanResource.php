<?php
namespace App\Http\Resources\InstallmentPlan;

use App\Http\Resources\Installment\InstallmentResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Http\Resources\InstallmentPayment\InstallmentPaymentResource;

class InstallmentPlanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'invoice_id' => $this->invoice_id,
            'total_amount' => $this->total_amount,
            'down_payment' => $this->down_payment,
            'installment_count' => $this->installment_count,
            'installment_amount' => $this->installment_amount,
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d H:i:s') : null,
            'due_day' => $this->due_day,
            'notes' => $this->notes,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'customer' => new UserResource($this->whenLoaded('customer')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'payments' => InstallmentPaymentResource::collection($this->whenLoaded('payments')),
            'installments' => InstallmentResource::collection($this->whenLoaded('installments')),
        ];
    }
}

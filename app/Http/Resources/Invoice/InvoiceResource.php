<?php
namespace App\Http\Resources\Invoice;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'invoice_type_id' => $this->invoice_type_id,
            'invoice_number' => $this->invoice_number,
            'issue_date' => $this->issue_date ? $this->issue_date->format('Y-m-d H:i:s') : null,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d H:i:s') : null,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'installment_plan' => new InstallmentPlanResource($this->whenLoaded('installmentPlan')),
        ];
    }
}

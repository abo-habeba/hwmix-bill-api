<?php

namespace App\Http\Resources\Invoice;

use App\Http\Resources\User\UserResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\InvoiceItem\InvoiceItemResource;
use App\Http\Resources\InvoiceType\InvoiceTypeResource;
use App\Http\Resources\InstallmentPlan\InstallmentPlanResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'invoice_type_id' => $this->invoice_type_id,
            'invoice_number' => $this->invoice_number,
            'issue_date' => optional($this->issue_date)->format('Y-m-d H:i:s'),
            'due_date' => optional($this->due_date)->format('Y-m-d H:i:s'),
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($this->updated_at)->format('Y-m-d H:i:s'),

            // العلاقات لما تكون محملة فقط
            'user' => new UserResource($this->whenLoaded('user')),
            'invoice_type' => new InvoiceTypeResource($this->whenLoaded('invoiceType')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'installment_plan' => new InstallmentPlanResource($this->whenLoaded('installmentPlan')),

            // بيانات إضافية
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'installment_plan_id' => $this->installment_plan_id,
        ];
    }
}

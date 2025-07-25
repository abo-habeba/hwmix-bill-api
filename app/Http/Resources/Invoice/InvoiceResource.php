<?php

namespace App\Http\Resources\Invoice;

use App\Http\Resources\User\UserResource;
use App\Http\Resources\User\UserBasicResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\InvoiceItem\InvoiceItemResource;
use App\Http\Resources\InvoiceType\InvoiceTypeResource;
use App\Http\Resources\InstallmentPlan\InstallmentPlanResource;
use App\Http\Resources\InstallmentPlan\InstallmentPlanBasicResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'invoice_type_id' => $this->invoice_type_id,
            'invoice_number' => $this->invoice_number,
            // الحقول المالية
            'gross_amount' => number_format($this->gross_amount, 2, '.', ''),
            'total_amount' => number_format($this->total_amount, 2, '.', ''),
            'paid_amount' => number_format($this->paid_amount, 2, '.', ''),
            'remaining_amount' => number_format($this->remaining_amount, 2, '.', ''),
            'round_step' => $this->round_step,
            'net_amount' => number_format($this->net_amount, 2, '.', ''),
            'total_discount' => number_format($this->total_discount, 2, '.', ''),

            'status' => $this->status,
            'status_label' => $this->getStatusLabel(), // إضافة status_label
            'notes' => $this->notes,

            'issue_date' => optional($this->issue_date)->format('Y-m-d H:i:s'),
            'due_date' => optional($this->due_date)->format('Y-m-d H:i:s'),
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($this->updated_at)->format('Y-m-d H:i:s'),

            // العلاقات لما تكون محملة فقط
            'user' => new UserBasicResource($this->whenLoaded('user')),
            'invoice_type' => new InvoiceTypeResource($this->whenLoaded('invoiceType')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'installment_plan' => new InstallmentPlanBasicResource($this->whenLoaded('installmentPlan')),

            // بيانات إضافية
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'installment_plan_id' => $this->installment_plan_id,
        ];
    }

    /**
     * ترجمة أو توصيف حالة الفاتورة.
     */
    protected function getStatusLabel()
    {
        return match ($this->status) {
            'draft' => 'مسودة',
            'confirmed' => 'مؤكدة',
            'canceled' => 'ملغاة',
            'paid' => 'مدفوعة بالكامل',
            'partially_paid' => 'مدفوعة جزئياً', // إضافة حالة محتملة
            default => 'غير معروفة',
        };
    }
}

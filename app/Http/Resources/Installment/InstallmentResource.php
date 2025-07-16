<?php

namespace App\Http\Resources\Installment;

use App\Http\Resources\InstallmentPlan\InstallmentPlanBasicResource;
use App\Http\Resources\User\UserBasicResource;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'installment_plan_id' => $this->installment_plan_id,
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d H:i:s') : null, // تنسيق التاريخ
            'amount' => number_format($this->amount, 2, '.', ''), // تنسيق الرقم
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(), // إضافة status_label
            'paid_at' => $this->paid_at ? $this->paid_at->format('Y-m-d H:i:s') : null, // تنسيق التاريخ
            'remaining' => number_format($this->remaining, 2, '.', ''), // تنسيق الرقم
            'created_by' => $this->created_by,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            'user' => new UserBasicResource($this->whenLoaded('user')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'installment_plan' => new InstallmentPlanBasicResource($this->whenLoaded('installmentPlan')),
        ];
    }

    /**
     * ترجمة أو توصيف حالة القسط.
     */
    protected function getStatusLabel()
    {
        return match ($this->status) {
            'pending' => 'في الانتظار',
            'paid' => 'مدفوع',
            'partially_paid' => 'مدفوع جزئياً',
            'canceled' => 'ملغى',
            'overdue' => 'متأخر', // حالة محتملة للأقساط
            default => 'غير معروف',
        };
    }
}

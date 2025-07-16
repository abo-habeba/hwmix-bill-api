<?php

namespace App\Http\Resources\InstallmentPlan;

use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPlanBasicResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name, // لو بتستخدمه في القوائم
            'total_amount' => $this->total_amount,
            'down_payment' => $this->down_payment ?? 0,
            'remaining_amount' => $this->remaining_amount ?? 0,
            'installment_count' => $this->installment_count,
            'installment_amount' => $this->installment_amount ?? 0,
            'number_of_installments' => $this->number_of_installments ?? $this->installment_count,
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'due_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'status' => $this->status ?? 'pending',
            'round_step' => $this->round_step ?? '10',
            'status_label' => $this->getStatusLabel(),
            // ممكن تضيفه لو بتعرض المستخدم في القائمة
            'user_id' => $this->user_id ?? null,
        ];
    }

    /**
     * ترجمة أو توصيف حالة الخطة.
     */
    protected function getStatusLabel()
    {
        return match ($this->status) {
            'pending' => 'في الانتظار',
            'active' => 'نشطة',
            'paid' => 'مدفوعة بالكامل',
            'cancelled' => 'ملغاة',
            default => 'غير معروفة',
        };
    }
}

<?php
namespace App\Http\Resources\InstallmentPlanSchedule;

use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentPlanScheduleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'installment_plan_id' => $this->installment_plan_id,
            'due_date' => $this->due_date,
            'installment_amount' => $this->installment_amount,
            'status' => $this->status,
            'paid_date' => $this->paid_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

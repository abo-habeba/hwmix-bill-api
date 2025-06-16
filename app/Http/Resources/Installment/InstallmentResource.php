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
            'due_date' => $this->due_date,
            'amount' => $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'remaining' => $this->remaining,
            'created_by' => $this->created_by,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserBasicResource($this->whenLoaded('user')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'installment_plan' => new InstallmentPlanBasicResource($this->whenLoaded('installmentPlan')),
        ];
    }
}

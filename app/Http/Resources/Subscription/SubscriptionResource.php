<?php
namespace App\Http\Resources\Subscription;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'service_id' => $this->service_id,
            'start_date' => $this->start_date,
            'next_billing_date' => $this->next_billing_date,
            'billing_cycle' => $this->billing_cycle,
            'price' => $this->price,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

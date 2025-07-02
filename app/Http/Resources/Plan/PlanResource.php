<?php

namespace App\Http\Resources\Plan;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'company_id' => $this->company_id,
            'price' => $this->price,
            'currency' => $this->currency,
            'duration' => $this->duration,
            'duration_unit' => $this->duration_unit,
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
            'features' => $this->features,
            'max_users' => $this->max_users,
            'max_projects' => $this->max_projects,
            'max_storage_mb' => $this->max_storage_mb,
            'type' => $this->type,
            'icon' => $this->icon,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // علاقات
            'company' => $this->whenLoaded('company'),
            'creator' => $this->whenLoaded('creator'),
            'updater' => $this->whenLoaded('updater'),
            'subscriptions' => $this->whenLoaded('subscriptions'),
        ];
    }
}

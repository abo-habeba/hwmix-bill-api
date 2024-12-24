<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'settings' => $this->settings,
            'last_login_at' => $this->last_login_at,
            'email_verified_at' => $this->email_verified_at,
            'roles' => $this->getRolesWithPermissions(),
            'permissions' => $this->getPermissions(),
            'balance' => $this->balance,
            'status' => $this->status,
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
            'company_id' => isset($this->company_id) ? $this->company_id->format('Y-m-d') : null,
            'created_by' => isset($this->created_by) ? $this->created_by->format('Y-m-d') : null,
        ];
    }
}

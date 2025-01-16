<?php

namespace App\Http\Resources\Roles;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Resources\Json\JsonResource;

class RolesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = Role::find($this->role->id);
        $permissions = $role->permissions->pluck('name');
        return [
            'id' => $this->role->id,
            'name' => $this->role->name,
            'guard_name' => $this->role->guard_name,
            'created_by' => $this->created_by,
            'company_id' => $this->company_id,
            'permissions' => $permissions,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}

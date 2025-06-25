<?php

namespace App\Http\Resources\Roles;

use App\Http\Resources\Company\CompanyBasicResource;
use App\Http\Resources\User\UserBasicResource;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->pluck('name');
            }),
            'companies' => $this->whenLoaded('companies', function () {
                return $this->companies->map(function ($company) {
                    $pivotData = [];
                    if (isset($company->pivot)) {
                        $pivotData = [
                            'created_by_user_id' => $company->pivot->created_by,
                            'created_by_user' => $company->pivot->created_by
                                ? new \App\Http\Resources\User\UserBasicResource(\App\Models\User::find($company->pivot->created_by))
                                : null,
                            'created_at' => optional($company->pivot->created_at)->format('Y-m-d H:i:s'),
                        ];
                    }
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'pivot' => $pivotData,
                    ];
                });
            }),
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($this->updated_at)->format('Y-m-d H:i:s'),
        ];
    }
}

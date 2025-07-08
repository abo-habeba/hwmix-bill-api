<?php

namespace App\Http\Resources\User;

use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Company\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

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
        $company = Company::with('logo')->find($this->company_id);

        $logoUrl = $company?->logo?->url ? asset('storage/' . $company->logo->url) : null;

        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'settings' => $this->settings,
            'company_logo' => $logoUrl,
            'last_login_at' => $this->last_login_at,
            'email_verified_at' => $this->email_verified_at,
            'roles' => $this->getRolesWithPermissions(),
            'balance' => $this->balanceBox() ?? 0,
            // الشركات التي يمكن للمستخدم الوصول إليها
            'companies' => CompanyResource::collection($this->getVisibleCompaniesForUser()),
            'cashBoxes' => CashBoxResource::collection($this->cashBoxesByCompany()),
            'status' => $this->status,
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'customer_type' => $this->customer_type,
        ];
    }

    protected function getVisibleCompaniesForUser()
    {
        // Debugging: تحقق من محتوى $this->companies
        \Log::info('Companies relationship for user: ' . $this->id, ['companies' => $this->companies->toArray()]);

        // Debugging: تأكد من أن $this->companies هو Collection فارغة إذا لم تكن هناك علاقات
        if ($this->companies->isEmpty()) {
            \Log::info('Companies relationship is empty for user: ' . $this->id);
        } else {
            \Log::info('Companies relationship is NOT empty for user: ' . $this->id . ', Count: ' . $this->companies->count());
        }

        // ... بقية الكود
        if ($this->hasPermissionTo(perm_key('admin.super'))) {
            return \App\Models\Company::all();
        }
        return $this->companies;
    }
}

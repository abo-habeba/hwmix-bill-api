<?php

namespace App\Http\Resources\CompanyUser;

use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class CompanyUserResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // هذا الـ Resource يتلقى نموذج CompanyUser
        // لذا، للوصول إلى بيانات المستخدم الأساسية، نستخدم $this->user
        // وللوصول إلى بيانات الشركة، نستخدم $this->company

        // الحصول على شعار الشركة من علاقة الشركة
        $companyLogoUrl = $this->whenLoaded('company', function () {
            return $this->company->logo?->url ? asset($this->company->logo->url) : null;
        });

        // الحصول على صورة الأفاتار للمستخدم من علاقة المستخدم
        $avatarImage = $this->whenLoaded('user', function () {
            return $this->user->images->where('type', 'avatar')->first();
        });
        $avatarUrl = $avatarImage ? asset($avatarImage->url) : null;

        return [
            // البيانات الأساسية للمستخدم (من جدول users)
            'id' => $this->user_id, // معرف المستخدم من جدول users
            'username' => $this->user->username,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'last_login_at' => $this->user->last_login_at,
            'email_verified_at' => $this->user->email_verified_at,
            'created_by' => $this->user->created_by, // من أنشأ المستخدم الأساسي

            // البيانات الخاصة بالشركة (من جدول company_user)
            'nickname' => $this->nickname_in_company, // الاسم المستعار الخاص بالشركة
            'full_name' => $this->full_name_in_company, // الاسم الكامل الخاص بالشركة
            'balance' => $this->balance_in_company, // الرصيد الخاص بالشركة
            'position' => $this->position_in_company, // المنصب الخاص بالشركة
            'status' => $this->status, // حالة المستخدم في هذه الشركة (من حقل status في company_user)
            'customer_type' => $this->customer_type_in_company, // نوع العميل الخاص بالشركة

            // بيانات الخزنة الافتراضية (من علاقة المستخدم الأساسي)
            // يجب التأكد أن cashBoxDefault في User Model يعتمد على company_id النشطة
            'cash_box_id' => optional($this->user->cashBoxDefault)->id,
            'cashBoxDefault' => new CashBoxResource($this->whenLoaded('user', fn() => $this->user->cashBoxDefault)),

            // بيانات الشركة النشطة (من علاقة company)
            'company_id' => $this->company_id, // معرف الشركة التي ينتمي إليها سجل company_user هذا
            'company_logo' => $companyLogoUrl, // شعار الشركة النشطة

            // علاقات أخرى من المستخدم الأساسي
            'roles' => $this->whenLoaded('user', fn() => $this->user->getRolesWithPermissions()),
            'avatar_url' => $avatarUrl,
            'companies' => $this->whenLoaded('user', fn() => CompanyResource::collection($this->user->getVisibleCompaniesForUser())),
            'cashBoxes' => CashBoxResource::collection($this->whenLoaded('user', fn() => $this->user->cashBoxes)),
            'permissions' => $this->whenLoaded('user', fn() => $this->user->getAllPermissions()->pluck('name')),

            // أوقات الإنشاء والتحديث لهذا السجل في company_user
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,

            // حقل settings إذا كان لا يزال موجودًا في User Model وتريد إرجاعه
            'settings' => $this->user->settings ?? null,
        ];
    }
}

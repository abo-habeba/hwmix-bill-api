<?php

namespace App\Http\Resources\CompanyUser;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class CompanyUserBasicResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        // الحصول على صورة الأفاتار للمستخدم من علاقة المستخدم
        $avatarImage = $this->whenLoaded('user', function () {
            return $this->user->images->where('type', 'avatar')->first();
        });
        $avatarUrl = $avatarImage ? asset($avatarImage->url) : null;

        return [
            // البيانات الأساسية للمستخدم (من جدول users)
            'id' => $this->user_id,
            'id_company_user' => $this->id,
            'username' =>  $this->user_username,
            'email' =>  $this->user_email,
            'phone' =>  $this->user_phone,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn() => $this->company->name),
            // البيانات الخاصة بالشركة (من جدول company_user)
            // هذه الحقول تعكس المفاتيح الموجودة في UserBasicResource
            'nickname' => $this->nickname_in_company,
            'balance' => $this->balance_in_company,
            'full_name' => $this->full_name_in_company,
            'customer_type' => $this->customer_type_in_company,
            'position' => $this->position_in_company,
            'status' => $this->status, // حالة المستخدم في هذه الشركة (من حقل status في company_user)

            // بيانات الخزنة الافتراضية (من علاقة المستخدم الأساسي)
            'cash_box_id' => $this->whenLoaded('user', fn() => optional($this->user->cashBoxDefault)->id),
            'avatar_url' => $avatarUrl,
            'created_at' => isset($this->created_at) ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => isset($this->updated_at) ? $this->updated_at->format('Y-m-d') : null,
        ];
    }
}

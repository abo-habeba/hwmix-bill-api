<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => 'nullable|string|max:15',
            'password' => 'nullable|string|min:8',
            'email' => "nullable|email",
            'full_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'username' => "nullable|string|max:255",
            'position' => 'nullable|string|max:255',
            'settings' => 'nullable|json',
            'last_login_at' => 'nullable|date',
            'email_verified_at' => 'nullable|date',
            'type' => 'nullable|in:system_owner,company_owner,sales,accounting,client,user',
            'balance' => 'nullable|numeric',
            'images_ids' => 'nullable|array',
            'status' => 'nullable|in:active,inactive',
            'company_id' => 'nullable|exists:companies,id',
            'company_ids' => 'nullable|array',
            'company_ids.*' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
            'customer_type' => 'nullable|in:retail,wholesale',
        ];
    }
}

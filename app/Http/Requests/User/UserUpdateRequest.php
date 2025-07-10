<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
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
    public function rules()
    {
        // $userId = $this->route('user');

        return [
            'email' => 'nullable|email|unique:users,email,' . optional($this->user)->id,
            'full_name' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,' . optional($this->user)->id,
            'nickname' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:15',
            'password' => 'nullable|string|min:8',
            'position' => 'nullable|string|max:255',
            'settings' => 'nullable|json',
            'last_login_at' => 'nullable',
            'email_verified_at' => 'nullable',
            'created_by' => 'nullable|exists:users,id',
            'images_ids' => 'nullable|exists:images,id',
            'balance' => 'nullable|numeric',
            'status' => 'nullable',
            'company_id' => 'nullable|exists:companies,id',
            'company_ids' => 'nullable|array',
            'company_ids.*' => 'nullable|exists:companies,id',
        ];
    }
}

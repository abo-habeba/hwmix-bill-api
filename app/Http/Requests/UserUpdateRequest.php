<?php

namespace App\Http\Requests;

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
            'email' => "nullable|email|unique:users,email,{$this->user->id}",
            'full_name' => 'nullable|string|max:255',
            'username' => "nullable|string|max:255|unique:users,username,{$this->user->id}",
            'nickname' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:15',
            'position' => 'nullable|string|max:255',
            'settings' => 'nullable|json',
            'last_login_at' => 'nullable',
            'email_verified_at' => 'nullable',
            'created_by' => 'nullable|exists:users,id',
            'balance' => 'nullable|numeric',
            'status' => 'nullable',
            'company_id' => 'nullable|exists:companies,id',
        ];
    }
}
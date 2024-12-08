<?php

namespace App\Http\Requests;

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
            'email' => 'required|email|unique:users,email,' . $this->user,
            'password' => 'required|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,' . $this->user,
            'phone' => 'nullable|string|max:15',
            'position' => 'nullable|string|max:255',
            'settings' => 'nullable|json',
            'last_login_at' => 'nullable|date',
            'email_verified_at' => 'nullable|date',
            'type' => 'nullable|in:system_owner,company_owner,sales,accounting,client,user',
            'balance' => 'nullable|numeric',
            'status' => 'nullable|in:active,inactive',
            'company_id' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
        ];
    }
}

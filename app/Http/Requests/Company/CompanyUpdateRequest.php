<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class CompanyUpdateRequest extends FormRequest
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

        return [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'field' => 'nullable|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:15',
            'email' => "nullable|email|unique:companies,email,{$this->company->id}",
            'created_by' => 'nullable|integer|exists:users,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];
    }
}

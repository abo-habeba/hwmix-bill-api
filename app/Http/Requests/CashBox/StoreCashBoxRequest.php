<?php

namespace App\Http\Requests\CashBox;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashBoxRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'cash_box_type_id' => ['required', 'exists:cash_box_types,id'],
            'is_default' => ['nullable', 'boolean'],
            'user_id' => ['nullable', 'exists:users,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'created_by' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'account_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}

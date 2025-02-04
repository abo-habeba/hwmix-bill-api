<?php

namespace App\Http\Requests\CashBox;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashBoxRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'balance' => 'nullable|numeric|min:0',
            'cash_type' => 'required|string|max:255|unique:cash_boxes,cash_type,' . $this->route('cash_box') . ',id,company_id,' . $this->company_id,
            'user_id' => 'required|exists:users,id',
            'company_id' => 'required|exists:companies,id',
        ];
    }
}

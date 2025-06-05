<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:0'],
            'reserved_quantity' => ['nullable', 'integer', 'min:0'],
            'expiry_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:available,unavailable,expired'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'created_by' => ['nullable', 'exists:users,id'],
            'updated_by' => ['nullable', 'exists:users,id'],
        ];
    }
}

<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockRequest extends FormRequest
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
            'qty' => 'sometimes|required|integer|min:0',
            'reserved' => 'sometimes|nullable|integer|min:0',
            'min_qty' => 'sometimes|nullable|integer|min:0',
            'cost' => 'sometimes|nullable|numeric|min:0',
            'batch' => 'sometimes|nullable|string|max:255',
            'expiry' => 'sometimes|nullable|date',
            'loc' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|required|in:available,unavailable,expired',
            'variant_id' => 'sometimes|required|exists:product_variants,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'company_id' => 'sometimes|required|exists:companies,id',
            'updated_by' => 'required|exists:users,id',
        ];
    }
}

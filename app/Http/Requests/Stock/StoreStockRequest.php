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
            'quantity' => 'required|integer|min:0',
            'reserved' => 'nullable|integer|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
            'batch' => 'nullable|string|max:255',
            'expiry' => 'nullable|date',
            'loc' => 'nullable|string|max:255',
            'status' => 'required|in:available,unavailable,expired',
            'variant_id' => 'required|exists:product_variants,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'company_id' => 'required|exists:companies,id',
            'created_by' => 'required|exists:users,id',
        ];
    }
}

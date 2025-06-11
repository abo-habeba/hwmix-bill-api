<?php

namespace App\Http\Requests\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductVariantRequest extends FormRequest
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
            'product_id' => 'sometimes|required|exists:products,id',
            'company_id' => 'sometimes|exists:companies,id',
            'created_by' => 'sometimes|exists:users,id',
            'barcode' => 'sometimes|nullable|string|max:255|unique:product_variants,barcode,' . $this->id,
            'sku' => 'sometimes|nullable|string|max:255|unique:product_variants,sku,' . $this->id,
            'retail_price' => 'sometimes|nullable|numeric|min:0',
            'wholesale_price' => 'sometimes|nullable|numeric|min:0',
            'profit_margin' => 'sometimes|nullable|numeric|min:0|max:100',
            'image' => 'sometimes|nullable|string|max:255',
            'weight' => 'sometimes|nullable|numeric|min:0',
            'dimensions' => 'sometimes|nullable|string|max:255',
            'tax' => 'sometimes|nullable|numeric|min:0|max:100',
            'discount' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|nullable|in:active,inactive,discontinued',
        ];
    }
}

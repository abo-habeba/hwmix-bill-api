<?php

namespace App\Http\Requests\ProductVariantAttribute;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductVariantAttributeRequest extends FormRequest
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
            'product_variant_id' => 'sometimes|required|exists:product_variants,id',
            'attribute_value_id' => 'sometimes|required|exists:attribute_values,id'
        ];
    }
}

<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'published_at' => 'sometimes|nullable|date',
            'desc' => 'sometimes|nullable|string',
            'desc_long' => 'sometimes|nullable|string',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'sometimes|nullable|exists:brands,id',
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'created_by' => 'sometimes|nullable|exists:users,id',
            'active' => 'sometimes',
            'featured' => 'sometimes',
            'returnable' => 'sometimes',
            'variants' => 'required|array|min:1',
            'variants.*.id' => 'prohibited',
            'variants.*.retail_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.wholesale_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.profit_margin' => 'sometimes|nullable|numeric|min:0|max:100',
            'variants.*.image' => 'sometimes|nullable|string|max:255',
            'variants.*.weight' => 'sometimes|nullable|numeric|min:0',
            'variants.*.dimensions' => 'sometimes|nullable|string|max:255',
            'variants.*.tax' => 'sometimes|nullable|numeric|min:0|max:100',
            'variants.*.discount' => 'sometimes|nullable|numeric|min:0',
            'variants.*.status' => 'sometimes|nullable|string|in:active,inactive,discontinued',
            'variants.*.created_by' => 'sometimes|nullable|exists:users,id',
            'variants.*.company_id' => 'sometimes|nullable|exists:companies,id',
            'variants.*.attributes' => 'sometimes|nullable|array',
            'variants.*.attributes.*.attribute_id' => 'sometimes|nullable|exists:attributes,id',
            'variants.*.attributes.*.attribute_value_id' => 'sometimes|nullable|exists:attribute_values,id',
            'variants.*.stocks' => 'required|array|min:1',
            'variants.*.stocks.*.id' => 'prohibited',
            'variants.*.stocks.*.quantity' => 'sometimes|nullable|integer|min:0',
            'variants.*.stocks.*.reserved' => 'sometimes|nullable|integer|min:0',
            'variants.*.stocks.*.expiry' => 'sometimes|nullable|date',
            'variants.*.stocks.*.status' => 'sometimes|nullable|string|in:available,unavailable,expired',
            'variants.*.stocks.*.batch' => 'sometimes|nullable|string|max:255',
            'variants.*.stocks.*.cost' => 'sometimes|nullable|numeric|min:0',
            'variants.*.stocks.*.loc' => 'sometimes|nullable|string|max:255',
            'variants.*.stocks.*.warehouse_id' => 'sometimes|nullable|exists:warehouses,id',
            'variants.*.stocks.*.company_id' => 'sometimes|nullable|exists:companies,id',
            'variants.*.stocks.*.created_by' => 'sometimes|nullable|exists:users,id',
            'variants.*.stocks.*.updated_by' => 'prohibited',
        ];

        return $rules;
    }
}

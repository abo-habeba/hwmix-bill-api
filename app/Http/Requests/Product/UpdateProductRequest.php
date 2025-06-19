<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $productId = $this->route('product')->id ?? null;

        $rules = [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'published_at' => 'sometimes|nullable|date',
            'desc' => 'sometimes|nullable|string',
            'desc_long' => 'sometimes|nullable|string',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'sometimes|nullable|exists:brands,id',
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'created_by' => 'sometimes|nullable|exists:users,id',
            'variants' => 'required|array|min:1',
            'variants.*.id' => 'sometimes|nullable|exists:product_variants,id',
            'variants.*.retail_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.wholesale_price' => 'sometimes|nullable|numeric|min:0',
            'variants.*.profit_margin' => 'sometimes|nullable|numeric|min:0|max:100',
            'variants.*.image' => 'sometimes|nullable|string|max:255',
            'variants.*.weight' => 'sometimes|nullable|numeric|min:0',
            'variants.*.dimensions' => 'sometimes|nullable|string|max:255',
            'variants.*.tax' => 'sometimes|nullable|numeric|min:0|max:100',
            'variants.*.discount' => 'sometimes|nullable|numeric|min:0',
            'variants.*.min_quantity' => 'sometimes|nullable|integer|min:0',
            'variants.*.status' => 'sometimes|nullable|string|in:active,inactive,discontinued',
            'variants.*.created_by' => 'sometimes|nullable|exists:users,id',
            'variants.*.company_id' => 'sometimes|nullable|exists:companies,id',
            // التعديل هنا: السماح بأن تكون الخصائص اختيارية تماماً أو تحتوي على قيم فارغة
            'variants.*.attributes' => 'sometimes|nullable|array',  // يمكن أن تكون موجودة كـ [] أو null
            'variants.*.attributes.*.attribute_id' => 'sometimes|nullable|exists:attributes,id',  // ليست مطلوبة إذا كانت موجودة
            'variants.*.attributes.*.attribute_value_id' => 'sometimes|nullable|exists:attribute_values,id',  // ليست مطلوبة إذا كانت موجودة
            'variants.*.stocks' => 'required|array|min:1',
            'variants.*.stocks.*.id' => 'sometimes|nullable|exists:stocks,id',
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
            'variants.*.stocks.*.updated_by' => 'sometimes|nullable|exists:users,id',
        ];

        foreach ($this->input('variants', []) as $index => $variant) {
            $variantId = $variant['id'] ?? null;

            $rules["variants.{$index}.barcode"] = [
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_variants', 'barcode')->ignore($variantId),
            ];

            $rules["variants.{$index}.sku"] = [
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_variants', 'sku')->ignore($variantId),
            ];
        }

        return $rules;
    }
}

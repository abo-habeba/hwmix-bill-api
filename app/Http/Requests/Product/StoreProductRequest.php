<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug',
            'meta_data' => 'nullable|json',
            'published_at' => 'nullable|date',
            'description' => 'nullable|string',
            'description_long' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            // المتغيرات
            'variants' => 'nullable|array',
            'variants.*.barcode' => 'nullable|string|max:255',
            'variants.*.sku' => 'nullable|string|max:255',
            'variants.*.retail_price' => 'nullable|numeric|min:0',
            'variants.*.wholesale_price' => 'nullable|numeric|min:0',
            'variants.*.profit_margin' => 'nullable|numeric|min:0|max:100',
            'variants.*.image' => 'nullable|string|max:255',
            'variants.*.weight' => 'nullable|numeric|min:0',
            'variants.*.dimensions' => 'nullable|string|max:255',
            'variants.*.tax' => 'nullable|numeric|min:0|max:100',
            'variants.*.discount' => 'nullable|numeric|min:0',
            'variants.*.status' => 'required|in:active,inactive,discontinued',
            // الخصائص
            'variants.*.attributes' => 'nullable|array',
            'variants.*.attributes.*.attribute_id' => 'nullable|exists:attributes,id',
            'variants.*.attributes.*.attribute_value_id' => 'nullable|exists:attribute_values,id',
            // المخزون الخاص بكل متغير
            'variants.*.stock' => 'nullable|array',
            'variants.*.stock.quantity' => 'nullable|integer|min:0',
            'variants.*.stock.reserved_quantity' => 'nullable|integer|min:0',
            'variants.*.stock.expiry_date' => 'nullable|date',
            'variants.*.stock.status' => 'nullable|string|max:50',
            'variants.*.stock.batch_no' => 'nullable|string|max:255',
            'variants.*.stock.unit_cost' => 'nullable|numeric|min:0',
            'variants.*.stock.location' => 'nullable|string|max:255',
        ];
    }
}

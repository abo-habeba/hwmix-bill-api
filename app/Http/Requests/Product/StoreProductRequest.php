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
        'category_id' => 'required|exists:categories,id', // لو عايز يكون nullable بدل required
        'brand_id' => 'nullable|exists:brands,id',
        'warehouse_id' => 'required|exists:warehouses,id',

        // تعريف قواعد التحقق لـ variants (لو عايز)
        'variants' => 'nullable|array',
        'variants.*.barcode' => 'nullable|string|max:255',
        'variants.*.sku' => 'nullable|string|max:255',
        'variants.*.purchase_price' => 'nullable|numeric|min:0',
        'variants.*.wholesale_price' => 'nullable|numeric|min:0',
        'variants.*.retail_price' => 'nullable|numeric|min:0',
        'variants.*.stock_threshold' => 'nullable|integer|min:0',
        'variants.*.status' => 'nullable|string|max:50',
        'variants.*.expiry_date' => 'nullable|date',
        'variants.*.image_url' => 'nullable|string|max:255',
        'variants.*.weight' => 'nullable|numeric|min:0',
        'variants.*.dimensions' => 'nullable|string|max:255',
        'variants.*.tax_rate' => 'nullable|numeric|min:0',
        'variants.*.discount' => 'nullable|numeric|min:0',

        // خاصية attributes داخل المتغير
        'variants.*.attributes' => 'nullable|array',
        'variants.*.attributes.*.attribute_id' => 'nullable|exists:attributes,id',
'variants.*.attributes.*.attribute_value_id' => 'nullable|exists:attribute_values,id',
        // خاصية stock داخل المتغير
        'variants.*.stock' => 'nullable|array',
        'variants.*.stock.quantity' => 'nullable|integer|min:0',
        'variants.*.stock.expiry_date' => 'nullable|date',
        'variants.*.stock.status' => 'nullable|string|max:50',
    ];
}

}

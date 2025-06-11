<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|unique:products,slug,' . $this->product->id,
            'is_active' => 'sometimes',
            'featured' => 'sometimes',
            'is_returnable' => 'sometimes',
            'meta_data' => 'nullable|json',
            'published_at' => 'nullable|date',
            'description' => 'nullable|string',
            'description_long' => 'nullable|string',
            'company_id' => 'sometimes|required|exists:companies,id',
            'created_by' => 'sometimes|required|exists:users,id',
            'category_id' => 'sometimes|required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            // المتغيرات
            'variants' => 'nullable|array|distinct',
            'variants.*.id' => 'nullable|exists:variants,id',
            'variants.*.barcode' => 'nullable|string|max:255',
            'variants.*.sku' => 'nullable|string|max:255',
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
            // الخصائص
            'variants.*.attributes' => 'nullable|array|distinct',
            'variants.*.attributes.*.id' => 'nullable|exists:variant_attributes,id',
            'variants.*.attributes.*.attribute_id' => 'nullable|exists:attributes,id',
            'variants.*.attributes.*.attribute_value_id' => 'nullable|exists:attribute_values,id',
            // المخزون
            'variants.*.stock' => 'nullable|array',
            'variants.*.stock.quantity' => 'nullable|integer|min:0',
            'variants.*.stock.expiry_date' => 'nullable|date',
            'variants.*.stock.status' => 'nullable|string|max:50',
            'variants.*.stock.purchase_price' => 'nullable|numeric|min:0',  // ✅ مضافة هنا صح
        ];
    }
}

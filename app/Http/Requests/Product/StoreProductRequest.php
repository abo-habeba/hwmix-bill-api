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
            'slug' => 'nullable|required|string|unique:products,slug',
            'is_active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
            'is_returnable' => 'nullable|boolean',
            'meta_data' => 'nullable|json',
            'published_at' => 'nullable|date',
            'description' => 'nullable|string',
            'description_long' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'warehouse_id' => 'required|exists:warehouses,id'
        ];
    }
}

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
            'is_active' => 'sometimes|boolean',
            'featured' => 'sometimes|boolean',
            'is_returnable' => 'sometimes|boolean',
            'meta_data' => 'nullable|json',
            'published_at' => 'nullable|date',
            'description' => 'nullable|string',
            'description_long' => 'nullable|string',
            'company_id' => 'sometimes|required|exists:companies,id',
            'created_by' => 'sometimes|required|exists:users,id',
            'category_id' => 'sometimes|required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id'
        ];
    }
}

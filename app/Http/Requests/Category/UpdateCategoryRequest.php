<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'company_id'  => 'sometimes|nullable|exists:companies,id',
            'created_by'  => 'sometimes|nullable|exists:users,id',
        ];
    }
}

<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|exists:categories,id',
            'company_id'  => 'nullable|exists:companies,id',
            'created_by'  => 'nullable|exists:users,id',
        ];
    }
}

<?php

namespace App\Http\Requests\Attribute;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|nullable|string|max:255',
            'attribute_id' => 'nullable|exists:attributes,id',
            'company_id' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
            'name_value' => 'required|string|max:255',
            'value' => 'nullable|string|max:255',
        ];
    }
}

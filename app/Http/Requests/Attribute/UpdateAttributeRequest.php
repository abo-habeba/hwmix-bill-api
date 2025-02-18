<?php

namespace App\Http\Requests\Attribute;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttributeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'value' => 'sometimes|nullable|string|max:255',
            'company_id' => 'sometimes|required|exists:companies,id',
            'created_by' => 'sometimes|required|exists:users,id',
        ];
    }
}

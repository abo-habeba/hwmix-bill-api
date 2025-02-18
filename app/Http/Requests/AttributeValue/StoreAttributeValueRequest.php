<?php

namespace App\Http\Requests\AttributeValue;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeValueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'value' => 'nullable|string|max:255',
            'attribute_id' => 'required|exists:attributes,id',
            'created_by' => 'nullable|exists:users,id',
        ];
    }
}

<?php

namespace App\Http\Requests\AttributeValue;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttributeValueRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'color' => 'sometimes|nullable|string|max:255',
            'attribute_id' => 'sometimes|required|exists:attributes,id',
            'created_by' => 'sometimes|nullable|exists:users,id',
        ];
    }
}

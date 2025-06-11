<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
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
            'location' => 'sometimes|nullable|string|max:255',
            'manager' => 'sometimes|nullable|string|max:255',
            'capacity' => 'sometimes|nullable|integer|min:0',
            'status' => 'sometimes|required|in:active,inactive',
            'company_id' => 'sometimes|required|exists:companies,id',
            'created_by' => 'sometimes|required|exists:users,id',
        ];
    }
}

<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
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
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
            'manager_name' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer',
            'company_id' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
        ];
    }
}

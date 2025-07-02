<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // تحقق من الصلاحيات في الكنترولر
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'default_price' => 'sometimes|required|numeric|min:0',
            'company_id' => 'sometimes|required|exists:companies,id',
        ];
    }
}

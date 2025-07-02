<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // تحقق من الصلاحيات في الكنترولر
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'default_price' => 'required|numeric|min:0',
            'company_id' => 'required|exists:companies,id',
        ];
    }
}

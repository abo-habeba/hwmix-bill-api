<?php

namespace App\Http\Requests\Profit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // تحقق من الصلاحيات في الكنترولر
    }

    public function rules(): array
    {
        return [
            'source_type' => 'sometimes|required|string|max:255',
            'source_id' => 'sometimes|required|integer',
            'user_id' => 'nullable|exists:users,id',
            'company_id' => 'sometimes|required|exists:companies,id',
            'revenue_amount' => 'sometimes|required|numeric|min:0',
            'cost_amount' => 'sometimes|required|numeric|min:0',
            'profit_amount' => 'sometimes|required|numeric|min:0',
            'note' => 'nullable|string|max:255',
            'profit_date' => 'sometimes|required|date',
        ];
    }
}

<?php

namespace App\Http\Requests\Profit;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // تحقق من الصلاحيات في الكنترولر
    }

    public function rules(): array
    {
        return [
            'source_type' => 'required|string|max:255',
            'source_id' => 'required|integer',
            'user_id' => 'nullable|exists:users,id',
            'company_id' => 'required|exists:companies,id',
            'revenue_amount' => 'required|numeric|min:0',
            'cost_amount' => 'required|numeric|min:0',
            'profit_amount' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
            'profit_date' => 'required|date',
        ];
    }
}

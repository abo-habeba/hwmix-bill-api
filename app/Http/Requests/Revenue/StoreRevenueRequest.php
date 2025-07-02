<?php

namespace App\Http\Requests\Revenue;

use Illuminate\Foundation\Http\FormRequest;

class StoreRevenueRequest extends FormRequest
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
            'created_by' => 'nullable|exists:users,id',
            'wallet_id' => 'nullable|exists:cash_boxes,id',
            'company_id' => 'required|exists:companies,id',
            'amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'remaining_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:255',
            'revenue_date' => 'required|date',
        ];
    }
}

<?php

namespace App\Http\Requests\InstallmentPayment;

use Illuminate\Foundation\Http\FormRequest;

class PayInstallmentsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'installment_ids' => 'required|array',
            'installment_ids.*' => 'integer|exists:installments,id',
            'amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_box_id' => 'nullable|exists:cash_boxes,id',
            'installment_plan_id' => 'required|exists:installment_plans,id',
            'user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'paid_at' => 'nullable|date',
        ];
    }
}

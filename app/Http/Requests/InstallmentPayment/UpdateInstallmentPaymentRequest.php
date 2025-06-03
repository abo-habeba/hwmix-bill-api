<?php
namespace App\Http\Requests\InstallmentPayment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallmentPaymentRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'installment_plan_id' => 'sometimes|exists:installment_plans,id',
            'payment_date' => 'sometimes|date',
            'amount_paid' => 'sometimes|numeric|min:0',
            'payment_method' => 'sometimes|string',
            'notes' => 'nullable|string',
        ];
    }
}

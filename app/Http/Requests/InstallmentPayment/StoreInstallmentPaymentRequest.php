<?php
namespace App\Http\Requests\InstallmentPayment;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstallmentPaymentRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'installment_plan_id' => 'required|exists:installment_plans,id',
            'payment_date' => 'required|date',
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
        ];
    }
}

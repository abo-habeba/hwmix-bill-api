<?php

namespace App\Http\Requests\InstallmentPaymentDetail;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstallmentPaymentDetailRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'installment_payment_id' => 'required|exists:installment_payments,id',
            'installment_id' => 'required|exists:installments,id',
            'amount_paid' => 'required|numeric|min:0',
        ];
    }
}

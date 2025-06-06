<?php

namespace App\Http\Requests\InstallmentPaymentDetail;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallmentPaymentDetailRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'installment_payment_id' => 'sometimes|exists:installment_payments,id',
            'installment_id' => 'sometimes|exists:installments,id',
            'amount_paid' => 'sometimes|numeric|min:0',
        ];
    }
}

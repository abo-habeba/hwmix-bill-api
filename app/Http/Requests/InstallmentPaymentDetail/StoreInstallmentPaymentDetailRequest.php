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
            // تم تغيير 'installment_payment_id' ليرتبط بـ 'payment_id'
            'payment_id' => 'required|exists:payments,id',
            'installment_id' => 'required|exists:installments,id',
            'amount_paid' => 'required|numeric|min:0',
        ];
    }
}

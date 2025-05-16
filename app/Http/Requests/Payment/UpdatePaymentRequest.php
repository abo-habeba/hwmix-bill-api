<?php
namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'payment_date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0',
            'method' => 'sometimes|string',
            'notes' => 'nullable|string',
            'is_split' => 'sometimes|boolean',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
        ];
    }
}

<?php
namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'user_id' => 'required|exists:users,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string',
            'notes' => 'nullable|string',
            'is_split' => 'required|boolean',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
        ];
    }
}

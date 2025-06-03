<?php
namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'user_id' => 'required|exists:users,id',
            'service_id' => 'required|exists:services,id',
            'start_date' => 'required|date',
            'next_billing_date' => 'required|date',
            'billing_cycle' => 'required|string',
            'price' => 'required|numeric|min:0',
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ];
    }
}

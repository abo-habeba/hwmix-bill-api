<?php
namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'service_id' => 'sometimes|exists:services,id',
            'start_date' => 'sometimes|date',
            'next_billing_date' => 'sometimes|date',
            'billing_cycle' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|string',
            'notes' => 'nullable|string',
        ];
    }
}

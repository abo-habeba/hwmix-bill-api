<?php
namespace App\Http\Requests\Installment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallmentRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'installment_plan_id' => 'sometimes|exists:installment_plans,id',
            'due_date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|string',
            'paid_at' => 'nullable|date',
            'remaining' => 'nullable|numeric|min:0',
        ];
    }
}

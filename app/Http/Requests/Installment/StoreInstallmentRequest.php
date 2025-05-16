<?php
namespace App\Http\Requests\Installment;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstallmentRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'installment_plan_id' => 'required|exists:installment_plans,id',
            'due_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|string',
            'paid_at' => 'nullable|date',
            'remaining' => 'nullable|numeric|min:0',
        ];
    }
}

<?php
namespace App\Http\Requests\InstallmentPlan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallmentPlanRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'invoice_id' => 'sometimes|exists:invoices,id',
            'total_amount' => 'sometimes|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'installment_count' => 'sometimes|integer|min:1',
            'installment_amount' => 'sometimes|numeric|min:0',
            'start_date' => 'sometimes|date',
            'due_day' => 'sometimes|integer|min:1|max:31',
            'notes' => 'nullable|string',
        ];
    }
}

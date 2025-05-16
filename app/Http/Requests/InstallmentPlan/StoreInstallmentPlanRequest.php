<?php
namespace App\Http\Requests\InstallmentPlan;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstallmentPlanRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'user_id' => 'required|exists:users,id',
            'invoice_id' => 'required|exists:invoices,id',
            'total_amount' => 'required|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'installment_count' => 'required|integer|min:1',
            'installment_amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'due_day' => 'required|integer|min:1|max:31',
            'notes' => 'nullable|string',
        ];
    }
}

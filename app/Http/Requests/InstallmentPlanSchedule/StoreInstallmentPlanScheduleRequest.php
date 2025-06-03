<?php
namespace App\Http\Requests\InstallmentPlanSchedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstallmentPlanScheduleRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'installment_plan_id' => 'required|exists:installment_plans,id',
            'due_date' => 'required|date',
            'installment_amount' => 'required|numeric|min:0',
            'status' => 'required|string',
            'paid_date' => 'nullable|date',
        ];
    }
}

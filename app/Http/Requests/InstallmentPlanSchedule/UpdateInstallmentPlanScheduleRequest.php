<?php
namespace App\Http\Requests\InstallmentPlanSchedule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstallmentPlanScheduleRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'installment_plan_id' => 'sometimes|exists:installment_plans,id',
            'due_date' => 'sometimes|date',
            'installment_amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|string',
            'paid_date' => 'nullable|date',
        ];
    }
}

<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'invoice_type_id' => 'sometimes|exists:invoice_types,id',
            'invoice_type_code' => 'nullable|string',

            'invoice_number' => 'nullable|string|unique:invoices,invoice_number,' . $this->invoice,
            'issue_date' => 'sometimes|date',
            'due_date' => 'sometimes|date',

            // الحقول المالية
            'gross_amount' => 'sometimes|numeric|min:0',
            'total_discount' => 'nullable|numeric|min:0',
            'net_amount' => 'sometimes|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'remaining_amount' => 'nullable|numeric|min:0',
            'round_step' => 'nullable|integer',

            'status' => 'sometimes|string',
            'notes' => 'nullable|string',

            'company_id' => 'sometimes|exists:companies,id',
            'created_by' => 'sometimes|exists:users,id',
            'installment_plan_id' => 'nullable|exists:installment_plans,id',
        ];
    }
}

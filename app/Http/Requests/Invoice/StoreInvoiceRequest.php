<?php
namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'user_id' => 'required|exists:users,id',
            'invoice_type_id' => 'required|exists:invoice_types,id',
            'invoice_number' => 'nullable|string|unique:invoices,invoice_number',
            'due_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
            'installment_plan_id' => 'nullable|exists:installment_plans,id',
        ];
    }
}

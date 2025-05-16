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
            'invoice_number' => 'required|string|unique:invoices,invoice_number',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ];
    }
}

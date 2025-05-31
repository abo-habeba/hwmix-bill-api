<?php
namespace App\Http\Requests\InvoiceType;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceTypeRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'code' => 'sometimes|string|max:100|unique:invoice_types,code,' . $this->route('invoice_type'),
            'context' => 'sometimes|string|max:100',
        ];
    }
}

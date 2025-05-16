<?php
namespace App\Http\Requests\InvoiceType;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceTypeRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ];
    }
}

<?php
namespace App\Http\Requests\InvoiceItem;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceItemRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'invoice_id' => 'required|exists:invoices,id',
            'name' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'product_id' => 'nullable|exists:products,id',
        ];
    }
}

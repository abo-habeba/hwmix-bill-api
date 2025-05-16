<?php
namespace App\Http\Requests\InvoiceItem;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceItemRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() {
        return [
            'invoice_id' => 'sometimes|exists:invoices,id',
            'name' => 'sometimes|string',
            'quantity' => 'sometimes|integer|min:1',
            'unit_price' => 'sometimes|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'total' => 'sometimes|numeric|min:0',
            'product_id' => 'nullable|exists:products,id',
        ];
    }
}

<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id' => 'nullable|integer',
            'invoice_type_id' => 'required|integer',
            'invoice_type_code' => 'nullable|string',
            'status' => 'nullable|string',
            'total_amount' => 'required|numeric',
            'paid_amount' => 'nullable|numeric|min:0',
            'remaining_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.variant_id' => 'required|integer',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|integer',
            'items.*.unit_price' => 'required|numeric',
            'items.*.discount' => 'nullable|numeric',
            'items.*.total' => 'required|numeric',
            'items.*.attributes' => 'nullable|array',
            'items.*.attributes.*.id' => 'nullable|integer',
            'items.*.attributes.*.attribute_id' => 'nullable|integer',
            'items.*.attributes.*.attribute_value_id' => 'nullable|integer',
            'items.*.stocks' => 'nullable|array',
            'items.*.stocks.*.id' => 'nullable|integer',
            'items.*.stocks.*.quantity' => 'required|integer',
            'items.*.stocks.*.reserved' => 'nullable|integer',
            'items.*.stocks.*.min_quantity' => 'nullable|integer',
            'items.*.stocks.*.cost' => 'required|numeric',
            'items.*.stocks.*.batch' => 'nullable|string',
            'items.*.stocks.*.expiry' => 'nullable|date',
            'items.*.stocks.*.loc' => 'nullable|string',
            'items.*.stocks.*.status' => 'nullable|string',
            'user_id' => 'required|integer',
            'installment_plan' => 'nullable|array',
            'installment_plan.down_payment' => 'nullable|numeric',
            'installment_plan.number_of_installments' => 'nullable|integer',
            'installment_plan.installment_amount' => 'nullable|numeric',
            'installment_plan.total_amount' => 'nullable|numeric',
            'installment_plan.start_date' => 'nullable|date',
            'installment_plan.due_date' => 'nullable|date',
            'installment_plan.round_step' => 'nullable|integer',
            'due_date' => 'nullable|date',
            'cash_box_id' => 'nullable|integer|exists:cash_boxes,id',
        ];
    }
}

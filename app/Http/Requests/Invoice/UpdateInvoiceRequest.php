<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    public function rules()
    {
        $invoiceId = $this->route('invoice')?->id;

        return [
            'invoice_type_id' => 'sometimes|integer|exists:invoice_types,id',
            'invoice_type_code' => 'nullable|string',
            'invoice_number' => [
                'nullable',
                'string',
                Rule::unique('invoices', 'invoice_number')->ignore($invoiceId),
            ],
            'status' => 'nullable|string',

            // الحقول المالية
            'gross_amount' => 'sometimes|numeric',
            'total_discount' => 'nullable|numeric',
            'net_amount' => 'sometimes|numeric',

            'paid_amount' => 'nullable|numeric',
            'remaining_amount' => 'nullable|numeric',
            'round_step' => 'nullable|integer',

            'due_date' => 'nullable|date',
            'cash_box_id' => 'nullable|integer|exists:cash_boxes,id',
            'user_cash_box_id' => 'nullable|integer|exists:cash_boxes,id',
            'user_id' => 'sometimes|integer|exists:users,id',

            'notes' => 'nullable|string',

            'items' => 'sometimes|array',
            'items.*.product_id' => 'required_with:items|integer',
            'items.*.variant_id' => 'sometimes|nullable|integer|exists:product_variants,id',
            'items.*.name' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|integer',
            'items.*.unit_price' => 'required_with:items|numeric',
            'items.*.discount' => 'nullable|numeric',
            'items.*.total' => 'required_with:items|numeric',

            'items.*.attributes' => 'nullable|array',
            'items.*.attributes.*.id' => 'nullable|integer',
            'items.*.attributes.*.attribute_id' => 'nullable|integer',
            'items.*.attributes.*.attribute_value_id' => 'nullable|integer',

            'items.*.stocks' => 'nullable|array',
            'items.*.stocks.*.id' => 'nullable|integer',
            'items.*.stocks.*.quantity' => 'required_with:items|integer',
            'items.*.stocks.*.reserved' => 'nullable|integer',
            'items.*.stocks.*.min_quantity' => 'nullable|integer',
            'items.*.stocks.*.cost' => 'required_with:items|numeric',
            'items.*.stocks.*.batch' => 'nullable|string',
            'items.*.stocks.*.expiry' => 'nullable|date',
            'items.*.stocks.*.loc' => 'nullable|string',
            'items.*.stocks.*.status' => 'nullable|string',

            'installment_plan' => 'nullable|array',
            'installment_plan.down_payment' => 'nullable|numeric',
            'installment_plan.number_of_installments' => 'nullable|integer',
            'installment_plan.installment_amount' => 'nullable|numeric',
            'installment_plan.total_amount' => 'nullable|numeric',
            'installment_plan.start_date' => 'nullable|date',
            'installment_plan.due_date' => 'nullable|date',
            'installment_plan.round_step' => 'nullable|integer',
        ];
    }
}

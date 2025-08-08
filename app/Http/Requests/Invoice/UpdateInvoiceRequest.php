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
            'invoice_type_code' => 'nullable|string|max:255', // إضافة max
            'invoice_number' => [
                'nullable',
                'string',
                'max:255', // إضافة max
                Rule::unique('invoices', 'invoice_number')->ignore($invoiceId),
            ],
            // تحديث قواعد 'status' لتشمل الحالات الجديدة
            'status' => ['nullable', 'string', Rule::in(['draft', 'confirmed', 'canceled', 'partially_paid', 'paid'])],

            // الحقول المالية
            'gross_amount' => 'sometimes|numeric|min:0', // إضافة min
            'total_discount' => 'nullable|numeric|min:0', // إضافة min
            'net_amount' => 'sometimes|numeric|min:0', // إضافة min

            'paid_amount' => 'nullable|numeric|min:0', // إضافة min
            'remaining_amount' => 'nullable|numeric|min:0', // إضافة min

            // إضافة حقل الربح التقديري للفاتورة
            'estimated_profit' => 'nullable|numeric|min:0',

            'round_step' => 'nullable|integer',

            'due_date' => 'nullable|date',
            'cash_box_id' => 'nullable|integer|exists:cash_boxes,id',
            'user_cash_box_id' => 'nullable|integer|exists:cash_boxes,id', // إذا كان هذا الحقل لا يزال مستخدماً
            'user_id' => 'sometimes|integer|exists:users,id', // إضافة exists rule

            'notes' => 'nullable|string|max:1000', // إضافة max

            'items' => 'sometimes|array|min:1', // يجب أن تحتوي الفاتورة على بند واحد على الأقل عند التحديث
            'items.*.product_id' => 'nullable|integer|exists:products,id', // يمكن أن يكون المنتج null إذا كان "خدمة" أو "بند نصي"
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id', // يمكن أن يكون null
            'items.*.name' => 'required_with:items|string|max:255', // اسم البند مطلوب، إضافة max
            'items.*.quantity' => 'required_with:items|numeric|min:0.01', // الكمية يجب أن تكون أكبر من صفر
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            // إضافة حقل سعر التكلفة لبنود الفاتورة
            'items.*.cost_price' => 'required_with:items|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0', // إضافة min
            'items.*.total' => 'required_with:items|numeric|min:0', // إضافة min

            'items.*.attributes' => 'nullable|array',
            'items.*.attributes.*.id' => 'nullable|integer',
            'items.*.attributes.*.attribute_id' => 'nullable|integer|exists:attributes,id',
            'items.*.attributes.*.attribute_value_id' => 'nullable|integer|exists:attribute_values,id',

            'items.*.stocks' => 'nullable|array',
            'items.*.stocks.*.id' => 'nullable|integer',
            'items.*.stocks.*.quantity' => 'required_with:items|integer|min:0',
            'items.*.stocks.*.reserved' => 'nullable|integer|min:0',
            'items.*.stocks.*.min_quantity' => 'nullable|integer|min:0',
            'items.*.stocks.*.cost' => 'required_with:items|numeric|min:0',
            'items.*.stocks.*.batch' => 'nullable|string|max:255', // إضافة max
            'items.*.stocks.*.expiry' => 'nullable|date',
            'items.*.stocks.*.loc' => 'nullable|string|max:255', // إضافة max
            'items.*.stocks.*.status' => 'nullable|string|max:255', // يمكن إضافة Rule::in إذا كانت الحالات معروفة، إضافة max

            'installment_plan' => 'nullable|array',
            'installment_plan.down_payment' => 'nullable|numeric|min:0', // إضافة min
            'installment_plan.number_of_installments' => 'nullable|integer|min:1', // إضافة min
            'installment_plan.installment_amount' => 'nullable|numeric|min:0', // إضافة min
            'installment_plan.total_amount' => 'nullable|numeric|min:0', // إضافة min
            'installment_plan.start_date' => 'nullable|date',
            'installment_plan.due_date' => 'nullable|date',
            'installment_plan.round_step' => 'nullable|integer',
        ];
    }
}

<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // استيراد Rule لاستخدامه مع enum

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
            'invoice_type_id' => 'required|integer|exists:invoice_types,id', // إضافة exists rule
            'invoice_type_code' => 'nullable|string|max:255',
            // تحديث قواعد 'status' لتشمل الحالات الجديدة
            'status' => ['nullable', 'string', Rule::in(['draft', 'confirmed', 'canceled', 'partially_paid', 'paid'])],

            // الحقول المالية
            'gross_amount' => 'required|numeric|min:0',      // إجمالي قبل الخصم
            'total_discount' => 'nullable|numeric|min:0', // الخصم العام
            'net_amount' => 'required|numeric|min:0',    // بعد الخصم
            'paid_amount' => 'nullable|numeric|min:0',
            'remaining_amount' => 'nullable|numeric|min:0',

            // إضافة حقل الربح التقديري للفاتورة
            'estimated_profit' => 'nullable|numeric|min:0',

            'round_step' => 'nullable|integer',

            'due_date' => 'nullable|date',
            'cash_box_id' => 'nullable|integer|exists:cash_boxes,id',
            'user_cash_box_id' => 'nullable|integer|exists:cash_boxes,id', // إذا كان هذا الحقل لا يزال مستخدماً
            'user_id' => 'required|integer|exists:users,id', // إضافة exists rule

            'notes' => 'nullable|string|max:1000',

            'items' => 'required|array|min:1', // يجب أن تحتوي الفاتورة على بند واحد على الأقل
            'items.*.product_id' => 'nullable|integer|exists:products,id', // يمكن أن يكون المنتج null إذا كان "خدمة" أو "بند نصي"
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id', // يمكن أن يكون null
            'items.*.name' => 'required|string|max:255', // اسم البند مطلوب
            'items.*.quantity' => 'required|numeric|min:0.01', // الكمية يجب أن تكون أكبر من صفر
            'items.*.unit_price' => 'required|numeric|min:0',
            // إضافة حقل سعر التكلفة لبنود الفاتورة
            'items.*.cost_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'items.*.attributes' => 'nullable|array',
            'items.*.attributes.*.id' => 'nullable|integer',
            'items.*.attributes.*.attribute_id' => 'nullable|integer|exists:attributes,id',
            'items.*.attributes.*.attribute_value_id' => 'nullable|integer|exists:attribute_values,id',
            'items.*.stocks' => 'nullable|array',
            'items.*.stocks.*.id' => 'nullable|integer',
            'items.*.stocks.*.quantity' => 'required|integer|min:0',
            'items.*.stocks.*.reserved' => 'nullable|integer|min:0',
            'items.*.stocks.*.min_quantity' => 'nullable|integer|min:0',
            'items.*.stocks.*.cost' => 'required|numeric|min:0',
            'items.*.stocks.*.batch' => 'nullable|string|max:255',
            'items.*.stocks.*.expiry' => 'nullable|date',
            'items.*.stocks.*.loc' => 'nullable|string|max:255',
            'items.*.stocks.*.status' => 'nullable|string|max:255', // يمكن إضافة Rule::in إذا كانت الحالات معروفة

            'installment_plan' => 'nullable|array',
            'installment_plan.down_payment' => 'nullable|numeric|min:0',
            'installment_plan.number_of_installments' => 'nullable|integer|min:1',
            'installment_plan.installment_amount' => 'nullable|numeric|min:0',
            'installment_plan.total_amount' => 'nullable|numeric|min:0',
            'installment_plan.start_date' => 'nullable|date',
            'installment_plan.due_date' => 'nullable|date',
            'installment_plan.round_step' => 'nullable|integer',
        ];
    }
}

<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; // استيراد Rule لاستخدامه مع payable_type

class UpdatePaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'payment_date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0',
            'method' => 'sometimes|string|max:255', // 'method' أصبح حقلاً نصياً
            'notes' => 'nullable|string|max:1000',
            'is_split' => 'sometimes|boolean',

            // الحقول الجديدة التي أضيفت لجدول payments
            'payment_type' => ['sometimes', 'string', Rule::in(['inflow', 'outflow'])], // يجب أن يكون 'inflow' أو 'outflow'
            'cash_box_id' => 'sometimes|exists:cash_boxes,id', // يجب أن يكون موجوداً في جدول cash_boxes
            // 'financial_transaction_id' لا يتم إرساله من الواجهة الأمامية عادةً، يتم إنشاؤه في الكنترولر
            // 'financial_transaction_id' => 'nullable|exists:financial_transactions,id', // إذا كان سيتم إرساله

            // حقول العلاقة Polymorphic
            'payable_type' => [
                'nullable',
                'string',
                // يمكنك إضافة قواعد للتحقق من أن النوع المدخل هو نموذج موجود
                // مثال: Rule::in(['App\\Models\\Invoice', 'App\\Models\\Installment', 'App\\Models\\Order'])
                // أو التحقق من وجود الكلاس
                function ($attribute, $value, $fail) {
                    if ($value && !class_exists($value)) {
                        $fail("The {$attribute} is not a valid model class.");
                    }
                },
            ],
            'payable_id' => 'nullable|required_with:payable_type|numeric', // يتطلب payable_type إذا كان موجوداً
        ];
    }
}

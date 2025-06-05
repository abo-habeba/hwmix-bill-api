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
            // بيانات الفاتورة الأساسية
            'user_id' => 'required|exists:users,id',
            'invoice_type_id' => 'required|exists:invoice_types,id',
            'invoice_number' => 'nullable|string|unique:invoices,invoice_number',
            'due_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'invoice_type_code' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
            'created_by' => 'nullable|exists:users,id',
            'installment_plan_id' => 'nullable|exists:installment_plans,id',
            // بيانات العناصر (المنتجات في الفاتورة)
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'items.*.company_id' => 'nullable|exists:companies,id',
            // بيانات خطة الأقساط (اختياري لو الفاتورة بالتقسيط)
            'installment_plan' => 'nullable|array',
            'installment_plan.start_date' => 'required_with:installment_plan|date',
            'installment_plan.number_of_installments' => 'required_with:installment_plan|integer|min:1',
            'installment_plan.installment_amount' => 'required_with:installment_plan|numeric|min:0',
            'installment_plan.total_amount' => 'required_with:installment_plan|numeric|min:0',
            'installment_plan.down_payment' => 'nullable|numeric|min:0',
            'installment_plan.status' => 'nullable|string',
            'installment_plan.company_id' => 'nullable|exists:companies,id',
            'installment_plan.created_by' => 'nullable|exists:users,id',
        ];
    }
}

<?php

namespace App\Http\Resources\Invoice;

use App\Http\Resources\User\UserResource;
use App\Http\Resources\User\UserBasicResource;
use App\Http\Resources\CashBox\CashBoxResource;
use App\Http\Resources\Company\CompanyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\InvoiceItem\InvoiceItemResource;
use App\Http\Resources\InvoiceType\InvoiceTypeResource;
use App\Http\Resources\InstallmentPlan\InstallmentPlanResource;
use App\Http\Resources\InstallmentPlan\InstallmentPlanBasicResource;
use App\Http\Resources\Payment\PaymentResource; // إضافة مورد الدفعات

class InvoiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'invoice_type_id' => $this->invoice_type_id,
            'invoice_number' => $this->invoice_number,

            // الحقول المالية
            'gross_amount' => number_format($this->gross_amount, 2, '.', ''),
            'total_discount' => number_format($this->total_discount, 2, '.', ''), // تم نقلها هنا
            'net_amount' => number_format($this->net_amount, 2, '.', ''), // تم نقلها هنا
            'estimated_profit' => number_format($this->estimated_profit, 2, '.', ''), // إضافة الربح التقديري
            'paid_amount' => number_format($this->paid_amount, 2, '.', ''),
            'remaining_amount' => number_format($this->remaining_amount, 2, '.', ''),
            'round_step' => $this->round_step,
            // 'total_amount' غير موجود في الهيكل الجديد، إذا كان محسوبًا، يجب حسابه هنا
            // أو التأكد من وجوده في النموذج إذا كان عمودًا في قاعدة البيانات.
            // بناءً على الهيكل الذي أرسلته، لا يوجد عمود 'total_amount'
            // سأفترض أنه كان خطأ أو حقلًا محسوبًا سابقًا.

            'status' => $this->status,
            'status_label' => $this->getStatusLabel(), // إضافة status_label
            'notes' => $this->notes,

            // 'issue_date' غير موجود في هيكل جدول الفواتير، تم إزالته
            'due_date' => optional($this->due_date)->format('Y-m-d H:i:s'),
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($this->updated_at)->format('Y-m-d H:i:s'),

            // العلاقات لما تكون محملة فقط
            'user' => new UserBasicResource($this->whenLoaded('user')),
            'invoice_type' => new InvoiceTypeResource($this->whenLoaded('invoiceType')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'creator' => new UserBasicResource($this->whenLoaded('creator')),
            'installment_plan' => new InstallmentPlanBasicResource($this->whenLoaded('installmentPlan')),
            'cash_box' => new CashBoxResource($this->whenLoaded('cashBox')), // إضافة مورد الصندوق النقدي
            'payments' => PaymentResource::collection($this->whenLoaded('payments')), // إضافة مورد الدفعات

            // بيانات إضافية
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'installment_plan_id' => $this->installment_plan_id,
            'cash_box_id' => $this->cash_box_id, // إضافة معرف الصندوق النقدي
        ];
    }

    /**
     * ترجمة أو توصيف حالة الفاتورة.
     */
    protected function getStatusLabel()
    {
        return match ($this->status) {
            'draft' => 'مسودة',
            'confirmed' => 'مؤكدة',
            'canceled' => 'ملغاة',
            'paid' => 'مدفوعة بالكامل',
            'partially_paid' => 'مدفوعة جزئياً', // إضافة حالة محتملة
            default => 'غير معروفة',
        };
    }
}

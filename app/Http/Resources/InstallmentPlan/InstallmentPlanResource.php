<?php

namespace App\Http\Resources\InstallmentPlan;

use App\Http\Resources\User\UserResource;
use App\Http\Resources\Invoice\InvoiceResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Installment\InstallmentResource;
use App\Http\Resources\InvoiceItem\InvoiceItemResource;
use App\Http\Resources\InstallmentPayment\InstallmentPaymentResource;

class InstallmentPlanResource extends JsonResource
{
    public function toArray($request)
    {
        $installments = $this->installments;  // العلاقة محمَّلة بالفعل؟

        $totalRemaining = $installments->reduce(fn($c, $inst) => bcadd($c, $inst->remaining, 2), '0.00');
        $totalPay = bcsub($this->total_amount, $totalRemaining, 2);
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'invoice_id' => $this->invoice_id,
            'total_amount' => number_format($this->total_amount, 2, '.', ''),
            'total_remaining' => number_format($totalRemaining, 2, '.', ''),  // ← 812.50
            'total_pay' => number_format($totalPay, 2, '.', ''),  // ← 453.00
            'down_payment' => $this->down_payment,
            'installment_count' => $this->installment_count,
            'installment_amount' => $this->installment_amount,
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d H:i:s') : null,
            'due_day' => $this->due_day,
            'notes' => $this->notes,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'user' => new UserResource($this->whenLoaded('user')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            // 'invoice_items' => InvoiceItemResource::collection($this->invoice->items),

            'invoice_items' => InvoiceItemResource::collection(
                // نستخدم whenLoaded على علاقة 'invoice' أولاً للتأكد من تحميل الفاتورة
                $this->whenLoaded('invoice', function () {
                    return $this->invoice->items;
                })
            ),
            'payments' => InstallmentPaymentResource::collection($this->whenLoaded('payments')),
            'installments' => InstallmentResource::collection(
                $this->whenLoaded('installments')?->sortBy('due_date')
            ),
        ];
    }
}

<?php

namespace App\Http\Resources\InstallmentPlan;

use App\Http\Resources\User\UserResource;
use App\Http\Resources\User\UserBasicResource;
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

        $totalInstallmentsRemaining = $installments->reduce(fn($c, $inst) => bcadd($c, $inst->remaining, 2), '0.00');
        $totalInstallmentsAmount = $installments->reduce(fn($c, $inst) => bcadd($c, $inst->amount, 2), '0.00');
        $totalInstallmentsPay = bcsub($totalInstallmentsAmount, $totalInstallmentsRemaining,  2);
        $totalPay = bcsub($this->total_amount, $totalInstallmentsRemaining, 2);
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'invoice_id' => $this->invoice_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status ?? 'pending',
            'round_step' => $this->round_step ?? null,
            'remaining_amount' => $this->remaining_amount ?? 0,
            'number_of_installments' => $this->number_of_installments ?? $this->installment_count,

            'total_amount' => number_format($this->total_amount, 2, '.', ''),
            'down_payment' => $this->down_payment,
            'installment_count' => $this->installment_count,
            'installment_amount' => $this->installment_amount,

            'total_installments_remaining' => number_format($totalInstallmentsRemaining, 2, '.', ''),
            'total_installments_amount' => number_format($totalInstallmentsAmount, 2, '.', ''),
            'total_installments_pay' => number_format($totalInstallmentsPay, 2, '.', ''),
            'total_pay' => number_format($totalPay, 2, '.', ''),

            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d H:i:s') : null,
            'due_day' => $this->due_day,
            'notes' => $this->notes,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,

            'user' => new UserBasicResource($this->whenLoaded('user')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'invoice_items' => InvoiceItemResource::collection(
                $this->whenLoaded('invoice', function () {
                    return $this->invoice->items;
                })
            ),
            'payments' => InstallmentPaymentResource::collection($this->whenLoaded('payments')),
            'installments' => InstallmentResource::collection(
                $this->whenLoaded('installments')?->sortBy('due_date') ?? collect()
            ),
        ];
    }
}

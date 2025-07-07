<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Models\Installment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\InstallmentPlan;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPaymentDetail;

class InstallmentPaymentService
{
    public function payInstallments(array $installmentIds, float $amount, array $options = [])
    {
        DB::beginTransaction();

        try {
            $remainingAmount = $amount;
            $authUser = Auth::user();

            $installmentPlan = InstallmentPlan::findOrFail($options['installment_plan_id'] ?? null);

            if (!$installmentPlan) {
                throw new Exception('خطة التقسيط غير موجودة.');
            }

            $cashBoxId = $options['cash_box_id'] ?? $authUser->cashBoxeDefault?->id;
            if (!$cashBoxId) {
                throw new Exception('لم يتم تحديد صندوق النقد.');
            }

            $installmentPayment = InstallmentPayment::create([
                'installment_plan_id' => $installmentPlan->id,
                'company_id' => $installmentPlan->company_id,
                'created_by' => $authUser->id,
                'payment_date' => $options['paid_at'] ?? now(),
                'amount_paid' => $amount,
                'payment_method' => $options['payment_method'] ?? 'default',
                'notes' => $options['notes'] ?? '',
            ]);

            $installments = Installment::whereIn('id', $installmentIds)
                ->where('installment_plan_id', $installmentPlan->id)
                ->orderBy('due_date')
                ->get();

            if ($installments->isEmpty()) {
                throw new Exception('لم يتم العثور على أقساط صالحة للدفع.');
            }

            foreach ($installments as $installment) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $allocatedAmount = min($remainingAmount, $installment->remaining);
                $newRemaining = $installment->remaining - $allocatedAmount;
                $newStatus = $newRemaining <= 0 ? 'تم الدفع' : $installment->status;
                $newPaidAt = $newRemaining <= 0 ? ($options['paid_at'] ?? now()) : $installment->paid_at;

                $installment->update([
                    'remaining' => $newRemaining,
                    'status' => $newStatus,
                    'paid_at' => $newPaidAt,
                ]);

                $installment->logCreated('تم دفع مبلغ ' . $allocatedAmount . ' لهذا القسط.');

                // إنشاء تفاصيل الدفع
                $installmentPayment->installments()->attach($installment->id, [
                    'amount_paid' => $allocatedAmount,
                ]);

                $remainingAmount -= $allocatedAmount;
            }

            // ➕ زيادة رصيد المستخدم (الموظف اللي دفع) لو مختلف عن صاحب القسط
            $planOwnerId = $installmentPlan->user_id;

            if ($authUser->id !== $planOwnerId) {
                $authUser->deposit($amount, $cashBoxId);
                $authUser->logUpdated('زيادة الرصيد بقيمة ' . $amount . ' نتيجة دفع أقساط عن مستخدم آخر.');
            }

            DB::commit();

            return $installmentPayment->load('installments');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

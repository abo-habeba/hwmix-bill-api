<?php

namespace App\Services;

use Exception;
use App\Models\Payment;
use App\Models\Revenue;
use App\Models\ActivityLog;
use App\Models\Installment;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Models\InstallmentPlan;
use App\Models\InstallmentPaymentDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class InstallmentPaymentService
{
    public function payInstallments(array $installmentIds, float $amount, array $options = [])
    {
        DB::beginTransaction();

        try {
            $remainingAmount = $amount;
            $paymentMethodId = $options['payment_method_id'] ?? PaymentMethod::where('code', 'cash')->value('id');
            $cashBoxId = $options['cash_box_id'] ?? Auth::user()->cashBoxeDefault?->id;

            if (!$cashBoxId) {
                throw new Exception('Default cash box not found for the user.');
            }

            $payment = Payment::create([
                'payment_date' => $options['paid_at'] ?? now(),
                'user_id' => Auth::id(),
                'payment_method_id' => $paymentMethodId,
                'notes' => $options['notes'] ?? '',
                'amount' => $amount,
                'method' => $options['method'] ?? 'default',
            ]);

            if (!isset($options['installment_plan_id'])) {
                throw new Exception('installment_plan_id is required in options.');
            }

            $installmentPlan = InstallmentPlan::find($options['installment_plan_id']);
            $planOwnerId = $installmentPlan->user_id;
            $authUser = Auth::user();
            // إذا كان دافع القسط هو نفسه صاحب خطة التقسيط
            if ($planOwnerId && $authUser && $planOwnerId == $authUser->id) {
                app(\App\Services\UserSelfDebtService::class)
                    ->registerInstallmentPayment($authUser, $amount, 0, $cashBoxId, $installmentPlan->company_id ?? null);
            } else {
                // ❶ جلب الأقساط المستحقة فقط (غير مدفوعة بالكامل)
                $installments = $installmentPlan
                    ->installments()
                    ->where('remaining', '>', value: 0)
                    ->orderBy('due_date')
                    ->get();
                // ❷ التأكد إن فيه أقساط فعلاً تستحق الدفع
                if ($installments->isEmpty()) {
                    throw new Exception('جميع الأقساط المحددة مدفوعة بالكامل.');
                }
                $updatedInstallments = [];
                // ❸ توزيع المبلغ على الأقساط
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
                    // تسجيل في سجل الأحداث (logs)
                    $installment->logCreated('تم سداد مبلغ ' . $allocatedAmount . ' من القسط بنجاح.');
                    // ربط القسط بالدفعة
                    $payment->installments()->attach($installment->id, [
                        'allocated_amount' => $allocatedAmount,
                    ]);
                    // إنشاء سجل في جدول installment_payment_details
                    InstallmentPaymentDetail::create([
                        'installment_payment_id' => $payment->id,
                        'installment_id' => $installment->id,
                        'amount_paid' => $allocatedAmount,
                    ]);
                    $remainingAmount -= $allocatedAmount;
                    // إضافة القسط المعدل إلى المصفوفة
                    $updatedInstallments[] = $installment;
                }
                Revenue::create([
                    'cash_box_id' => $cashBoxId,
                    'amount' => $amount,
                    'revenue_date' => $options['paid_at'] ?? now(),
                    'note' => $options['notes'] ?? '',
                    'source_type' => $options['source_type'] ?? 'InstallmentPayment',
                    'source_id' => $payment->id,
                    'user_id' => Auth::id(),
                    'created_by' => Auth::id(),
                    'wallet_id' => $options['wallet_id'] ?? null,
                    'company_id' => $options['company_id'] ?? Auth::user()->company_id,
                    'payment_method' => $options['payment_method'] ?? 'default',
                ]);

                Transaction::create([
                    'user_id' => Auth::id(),
                    'cashbox_id' => $cashBoxId,
                    'target_user_id' => null,
                    'target_cashbox_id' => null,
                    'created_by' => Auth::id(),
                    'company_id' => $options['company_id'] ?? Auth::user()->company_id,
                    'type' => $options['type_ar'] ?? 'دفع قسط',
                    'amount' => $amount,
                    'balance_before' => null,
                    'balance_after' => null,
                    'description' => $options['notes'] ?? '',
                    'original_transaction_id' => null,
                ]);

                $user = Auth::user();
                $user->deposit($amount, $cashBoxId);

                $user->logUpdated('زيادة الرصيد بقيمة ' . $amount . ' نتيجة دفع الأقساط.');
            }

            DB::commit();

            return $updatedInstallments;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

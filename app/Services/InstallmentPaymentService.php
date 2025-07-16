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
use Illuminate\Support\Facades\Log;
use App\Models\PaymentMethod; // إضافة استيراد نموذج PaymentMethod

class InstallmentPaymentService
{
    /**
     * يدفع الأقساط المحددة، مع التعامل مع المدفوعات الجزئية والمبالغ الزائدة.
     *
     * @param array $installmentIds معرفات الأقساط المراد دفعها.
     * @param float $amount المبلغ الإجمالي المدفوع.
     * @param array $options خيارات إضافية (user_id, installment_plan_id, payment_method_id, cash_box_id, notes, paid_at).
     * @return \App\Models\InstallmentPayment الدفعة الرئيسية التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function payInstallments(array $installmentIds, float $amount, array $options = [])
    {
        DB::beginTransaction();

        try {
            $remainingAmount = $amount; // المبلغ المتبقي للدفع
            $totalAmountPaidInThisTransaction = 0; // المبلغ الفعلي الذي تم دفعه في هذه العملية
            $authUser = Auth::user();

            // جلب خطة التقسيط
            $installmentPlan = InstallmentPlan::find($options['installment_plan_id'] ?? null);
            if (!$installmentPlan) {
                throw new Exception('خطة التقسيط غير موجودة.');
            }

            // تحديد صندوق النقد للموظف (الذي يستلم الدفعة)
            $cashBoxId = $options['cash_box_id'] ?? $authUser->cashBoxeDefault?->id;
            if (!$cashBoxId) {
                throw new Exception('لم يتم تحديد صندوق نقدي افتراضي للموظف.');
            }

            // تحديد صندوق النقد للعميل (الذي يدفع القسط)
            $clientUser = User::find($installmentPlan->user_id);
            $clientCashBoxId = $options['user_cash_box_id'] ?? ($clientUser ? $clientUser->cashBoxeDefault?->id : null);
            if (!$clientUser || !$clientCashBoxId) {
                throw new Exception('لم يتم العثور على العميل أو صندوق نقدي افتراضي للعميل.');
            }

            // تحديد اسم طريقة الدفع بناءً على payment_method_id
            $paymentMethodName = 'unknown'; // قيمة افتراضية
            if (isset($options['payment_method_id'])) {
                $paymentMethod = PaymentMethod::find($options['payment_method_id']);
                if ($paymentMethod) {
                    $paymentMethodName = $paymentMethod->name; // افتراض أن حقل الاسم هو 'name' في جدول PaymentMethod
                }
            }

            // إنشاء سجل الدفعة الرئيسية
            $installmentPayment = InstallmentPayment::create([
                'installment_plan_id' => $installmentPlan->id,
                'company_id' => $installmentPlan->company_id,
                'created_by' => $authUser->id,
                'payment_date' => $options['paid_at'] ?? now(),
                'amount_paid' => $amount, // المبلغ الإجمالي الذي تم استلامه في هذه العملية
                'payment_method' => $paymentMethodName, // تم التغيير من payment_method_id إلى payment_method
                'notes' => $options['notes'] ?? '',
            ]);

            // 1. جلب الأقساط المراد دفعها أولاً (التي تم تحديد معرفاتها)
            $installmentsToPayInitially = Installment::whereIn('id', $installmentIds)
                ->where('installment_plan_id', $installmentPlan->id)
                ->where('status', '!=', 'canceled')
                ->where('remaining', '>', 0)
                ->orderBy('due_date')
                ->get();

            // 2. معالجة الأقساط المحددة أولاً
            foreach ($installmentsToPayInitially as $installment) {
                if ($remainingAmount <= 0) {
                    break; // لا يوجد المزيد من المبلغ للدفع
                }

                $amountToApplyToCurrentInstallment = min($remainingAmount, $installment->remaining);

                $newRemaining = $installment->remaining - $amountToApplyToCurrentInstallment;
                $newStatus = $installment->status;

                if ($newRemaining <= 0) {
                    $newStatus = 'paid'; // تم الدفع بالكامل
                } elseif ($amountToApplyToCurrentInstallment > 0 && $newRemaining > 0) {
                    $newStatus = 'partially_paid'; // مدفوع جزئيًا
                }

                // تحديث القسط
                $installment->update([
                    'remaining' => $newRemaining,
                    'status' => $newStatus,
                    'paid_at' => ($newStatus === 'paid') ? ($options['paid_at'] ?? now()) : $installment->paid_at,
                ]);

                // installment_payment_id
                // installment_id
                // amount_paid
                // تسجيل تفاصيل الدفع لهذا القسط المحدد
                InstallmentPaymentDetail::create([
                    'installment_payment_id' => $installmentPayment->id,
                    'installment_id' => $installment->id,
                    'amount_paid' => $amountToApplyToCurrentInstallment,
                ]);

                $remainingAmount -= $amountToApplyToCurrentInstallment;
                $totalAmountPaidInThisTransaction += $amountToApplyToCurrentInstallment;

                Log::info('[InstallmentPaymentService] تم دفع قسط جزئياً/كلياً (محدد).', [
                    'installment_id' => $installment->id,
                    'amount_applied' => $amountToApplyToCurrentInstallment,
                    'new_remaining' => $newRemaining,
                    'new_status' => $newStatus
                ]);
            }

            // 3. إذا تبقى مبلغ، قم بدفعه للأقساط الأخرى غير المدفوعة في نفس الخطة
            if ($remainingAmount > 0) {
                $additionalInstallments = Installment::where('installment_plan_id', $installmentPlan->id)
                    ->where('status', '!=', 'canceled')
                    ->where('remaining', '>', 0)
                    ->whereNotIn('id', $installmentIds) // استبعاد الأقساط التي تم معالجتها بالفعل
                    ->orderBy('due_date')
                    ->get();

                foreach ($additionalInstallments as $installment) {
                    if ($remainingAmount <= 0) {
                        break; // لا يوجد المزيد من المبلغ للدفع
                    }

                    $amountToApplyToCurrentInstallment = min($remainingAmount, $installment->remaining);

                    $newRemaining = $installment->remaining - $amountToApplyToCurrentInstallment;
                    $newStatus = $installment->status;

                    if ($newRemaining <= 0) {
                        $newStatus = 'paid';
                    } elseif ($amountToApplyToCurrentInstallment > 0 && $newRemaining > 0) {
                        $newStatus = 'partially_paid';
                    }

                    // تحديث القسط
                    $installment->update([
                        'remaining' => $newRemaining,
                        'status' => $newStatus,
                        'paid_at' => ($newStatus === 'paid') ? ($options['paid_at'] ?? now()) : $installment->paid_at,
                    ]);

                    // تسجيل تفاصيل الدفع لهذا القسط المحدد
                    InstallmentPaymentDetail::create([
                        'installment_payment_id' => $installmentPayment->id,
                        'installment_id' => $installment->id,
                        'amount_paid' => $amountToApplyToCurrentInstallment,
                    ]);

                    $remainingAmount -= $amountToApplyToCurrentInstallment;
                    $totalAmountPaidInThisTransaction += $amountToApplyToCurrentInstallment;

                    Log::info('[InstallmentPaymentService] تم دفع قسط إضافي جزئياً/كلياً (غير محدد).', [
                        'installment_id' => $installment->id,
                        'amount_applied' => $amountToApplyToCurrentInstallment,
                        'new_remaining' => $newRemaining,
                        'new_status' => $newStatus
                    ]);
                }
            }

            // تحديث المبلغ المدفوع الفعلي في سجل الدفعة الرئيسية
            $installmentPayment->update(['amount_paid' => $totalAmountPaidInThisTransaction]);

            // 1. إيداع المبلغ في خزنة الموظف (الذي استلم الدفعة)
            $depositResultStaff = $authUser->deposit($totalAmountPaidInThisTransaction, $cashBoxId);
            if ($depositResultStaff !== true) {
                throw new Exception('فشل إيداع المبلغ في خزنة الموظف: ' . json_encode($depositResultStaff));
            }
            Log::info('[InstallmentPaymentService] تم إيداع المبلغ في خزنة الموظف.', [
                'user_id' => $authUser->id,
                'cash_box_id' => $cashBoxId,
                'amount' => $totalAmountPaidInThisTransaction
            ]);

            // 2. إيداع المبلغ في رصيد العميل (لتقليل الدين)
            $depositResultClient = $clientUser->deposit($totalAmountPaidInThisTransaction, $clientCashBoxId);
            if ($depositResultClient !== true) {
                throw new Exception('فشل إيداع المبلغ في رصيد العميل: ' . json_encode($depositResultClient));
            }
            Log::info('[InstallmentPaymentService] تم إيداع المبلغ في رصيد العميل لتقليل الدين.', [
                'user_id' => $clientUser->id,
                'cash_box_id' => $clientCashBoxId,
                'amount' => $totalAmountPaidInThisTransaction
            ]);

            DB::commit();
            Log::info('[InstallmentPaymentService] تمت عملية دفع الأقساط بنجاح.', ['installment_payment_id' => $installmentPayment->id]);

            // إضافة خاصية ديناميكية للكائن للإشارة إلى المبلغ الزائد
            if ($remainingAmount > 0) {
                $installmentPayment->excess_amount = $remainingAmount;
                Log::info('[InstallmentPaymentService] تم دفع جميع الأقساط وبقي مبلغ زائد.', [
                    'installment_plan_id' => $installmentPlan->id,
                    'excess_amount' => $remainingAmount
                ]);
            }

            return $installmentPayment;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[InstallmentPaymentService] فشل في دفع الأقساط.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

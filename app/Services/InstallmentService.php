<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\User;
use App\Models\InstallmentPayment; // تم إضافة استيراد لنموذج دفعات الأقساط
use App\Models\InstallmentPaymentDetail; // تم إضافة استيراد لنموذج تفاصيل دفعات الأقساط
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InstallmentService
{
    /**
     * إنشاء خطة أقساط وأقساطها الشهرية.
     *
     * @param array $data بيانات الفاتورة وخطة الأقساط.
     * @param int $invoiceId معرف الفاتورة المرتبطة.
     * @return void
     * @throws \Throwable
     */
    public function createInstallments(array $data, int $invoiceId): void
    {
        try {
            Log::info('InstallmentService: بدء إنشاء خطة التقسيط.', ['invoice_id' => $invoiceId]);

            $planData = $data['installment_plan'];
            $userId = $data['user_id'];
            $startDate = Carbon::parse($planData['start_date']);
            $roundStep = isset($planData['round_step']) && $planData['round_step'] > 0 ? (int)$planData['round_step'] : 10;

            $totalAmount = $planData['total_amount'];
            $downPayment = $planData['down_payment'];
            $installmentsN = (int) $planData['number_of_installments'];

            $remaining = bcsub($totalAmount, $downPayment, 2);
            $avgInst = bcdiv($remaining, (string)$installmentsN, 2);
            $stdInst = number_format(ceil((float)$avgInst / $roundStep) * $roundStep, 2, '.', '');

            $planModel = InstallmentPlan::create([
                'invoice_id' => $invoiceId,
                'user_id' => $userId,
                'total_amount' => $totalAmount,
                'down_payment' => $downPayment,
                'remaining_amount' => $remaining,
                'number_of_installments' => $installmentsN,
                'installment_amount' => $stdInst,
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $startDate->copy()->addMonths($installmentsN)->format('Y-m-d'),
                'status' => 'pending',
                'notes' => $planData['notes'] ?? null,
            ]);
            Log::info('InstallmentService: تم إنشاء خطة التقسيط بنجاح.', ['plan_id' => $planModel->id]);

            $paidSum = '0.00';
            $count = 0;
            $lastDate = null;

            for ($i = 1; $i <= $installmentsN; $i++) {
                $left = bcsub($remaining, $paidSum, 2);
                if (bccomp($left, '0.00', 2) <= 0) break;

                $amount = (bccomp($stdInst, $left, 2) === 1 || $i === $installmentsN) ? $left : $stdInst;
                $due = $startDate->copy()->addMonths($i)->format('Y-m-d');

                Installment::create([
                    'installment_plan_id' => $planModel->id,
                    'installment_number' => $i,
                    'due_date' => $due,
                    'amount' => $amount,
                    'remaining' => $amount,
                    'status' => 'pending',
                    'user_id' => $userId,
                    'company_id' => $planModel->company_id, // ربط القسط بالشركة التابع لها خطة الأقساط
                ]);
                Log::info('InstallmentService: تم إنشاء قسط فردي.', ['installment_plan_id' => $planModel->id, 'installment_number' => $i, 'amount' => $amount]);

                $paidSum = bcadd($paidSum, $amount, 2);
                $lastDate = $due;
                $count = $i;
            }

            $planModel->update([
                'end_date' => $lastDate,
                'number_of_installments' => $count,
            ]);
            Log::info('InstallmentService: تم تحديث بيانات خطة التقسيط النهائية.', ['plan_id' => $planModel->id]);
        } catch (\Throwable $e) {
            Log::error('InstallmentService: فشل في إنشاء خطة التقسيط.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * إلغاء خطة الأقساط والأقساط الفردية المرتبطة بفاتورة، وعكس الدفعات المالية.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بخطة الأقساط.
     * @return float إجمالي المبالغ المدفوعة للأقساط الفردية التي تم عكسها.
     * @throws \Throwable
     */
    public function cancelInstallments(Invoice $invoice): float
    {
        Log::info('InstallmentService: بدء إلغاء الأقساط للفاتورة رقم: ' . $invoice->id);
        $totalReversedAmount = 0;

        if (!$invoice->installmentPlan) {
            Log::warning('InstallmentService: لا توجد خطة أقساط للفاتورة.', ['invoice_id' => $invoice->id]);
            return $totalReversedAmount;
        }

        $installmentPlan = $invoice->installmentPlan;

        // استرجاع جميع دفعات خطة التقسيط هذه
        $paymentsToReverse = InstallmentPayment::where('installment_plan_id', $installmentPlan->id)->get();

        foreach ($paymentsToReverse as $payment) {
            $paidAmount = $payment->amount_paid; // المبلغ الإجمالي المدفوع في هذه المعاملة

            if ($paidAmount > 0) {
                $totalReversedAmount += $paidAmount;
            }

            // حذف سجل الدفع وتفاصيله
            InstallmentPaymentDetail::where('installment_payment_id', $payment->id)->delete();
            Log::info('InstallmentService: تم حذف تفاصيل الدفع المرتبطة.', ['payment_id' => $payment->id]);
            $payment->delete();
            Log::info('InstallmentService: تم حذف سجل الدفع الرئيسي.', ['payment_id' => $payment->id]);
        }

        // تحديث حالة جميع الأقساط التابعة لخطة الأقساط إلى 'canceled'
        // باستخدام 'remaining' بدلاً من 'remaining_amount'
        // وتعيين 'paid_amount' إلى 0 (إذا كان هذا هو المطلوب للدلالة على عدم وجود مدفوعات)
        $installmentPlan->installments()->update([
            'status' => 'canceled',
            'remaining' => \DB::raw('amount'), // إعادة 'remaining' إلى قيمة 'amount' الأصلية للقسط
            'paid_at' => null,
        ]);
        Log::info('InstallmentService: تم تحديث حالة جميع الأقساط إلى ملغاة.', ['plan_id' => $installmentPlan->id]);

        // تحديث حالة خطة الأقساط إلى 'canceled'
        // هنا افترض أن installment_plans لديه remaining_amount أو عمود مشابه
        $installmentPlan->update(['status' => 'canceled', 'remaining_amount' => 0]);
        Log::info('InstallmentService: تم إلغاء خطة الأقساط.', ['installment_plan_id' => $installmentPlan->id]);

        return $totalReversedAmount;
    }
}

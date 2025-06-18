<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\InstallmentPlan;
use Carbon\Carbon;

/**
 * خدمة إنشاء خطط الأقساط والأقساط الفردية بدقة عالية
 *
 * المزايا:
 *  ▸ حسابات DECIMAL عبر BCMath (لا كسور عائمة)
 *  ▸ تقريب القسط القياسى لأعلى مضاعف متغير (1، 5، 10 …)
 *  ▸ لا يُنشئ قسطًا بقيمة صفر—يتوقف فور انتهاء المبلغ
 *  ▸ يُحدِّث عدد الأقساط الفعلى و end_date بعد الإنشاء
 */
class InstallmentService
{
    /**
     * أنشئ خطة أقساط وأقساطها.
     *
     * @param  array $data      البيانات القادمة من الـ Frontend
     * @param  int   $invoiceId رقم الفاتورة
     * @return void
     */
    public function createInstallments(array $data, int $invoiceId): void
    {
        /*-------------------------------------------------
        | 1. بيانات أساسية                               |
        --------------------------------------------------*/
        $planData = $data['installment_plan'];
        $userId = $data['user_id'];
        $startDate = Carbon::parse($planData['start_date']);
        $roundStep = isset($planData['round_step']) && $planData['round_step'] > 0
            ? (int) $planData['round_step']  // قيمة التقريب (1، 5، 10 …)
            : 10;  // افتراضى = 10

        /*-------------------------------------------------
        | 2. حساب المبالغ بدقة                            |
        --------------------------------------------------*/
        $totalAmount = $planData['total_amount'];  // إجمالى الفاتورة
        $downPayment = $planData['down_payment'];  // المقدم
        $installmentsN = (int) $planData['number_of_installments'];

        $remaining = bcsub($totalAmount, $downPayment, 2);  // المتبقّى
        $avgInst = bcdiv($remaining, $installmentsN, 2);  // متوسط القسط قبل التقريب

        // دالة تقريب ديناميكى لأعلى مضاعف roundStep
        $ceilTo = static function (string $val, int $step): string {
            $rounded = ceil((float) $val / $step) * $step;
            return number_format($rounded, 2, '.', '');
        };

        $stdInst = $ceilTo($avgInst, $roundStep);  // القسط القياسى بعد التقريب

        /*-------------------------------------------------
        | 3. إنشاء خطة الأقساط                            |
        --------------------------------------------------*/
        $planModel = InstallmentPlan::create([
            'invoice_id' => $invoiceId,
            'user_id' => $userId,
            'total_amount' => $totalAmount,
            'down_payment' => $downPayment,
            'remaining_amount' => $remaining,
            'number_of_installments' => $installmentsN,  // يُحدَّث لاحقًا بالعدد الفعلى
            'installment_amount' => $stdInst,
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $startDate->copy()->addMonths($installmentsN)->format('Y-m-d'),
            'status' => 'لم يتم الدفع',
            'notes' => $planData['notes'] ?? null,
        ]);

        /*-------------------------------------------------
        | 4. إنشاء الأقساط الفردية                        |
        --------------------------------------------------*/
        $paidSum = '0.00';
        $count = 0;
        $lastDate = null;

        for ($i = 1; $i <= $installmentsN; $i++) {
            $left = bcsub($remaining, $paidSum, 2);  // المتبقّى قبل هذا القسط
            if (bccomp($left, '0.00', 2) <= 0)
                break;  // توقّف إذا خلص المبلغ

            // لو القسط القياسى أكبر من المتبقّى ⇒ آخر قسط
            $amount = (bccomp($stdInst, $left, 2) === 1 || $i === $installmentsN)
                ? $left
                : $stdInst;

            $due = $startDate->copy()->addMonths($i)->format('Y-m-d');  // YYYY-MM-DD

            Installment::create([
                'installment_plan_id' => $planModel->id,
                'installment_number' => $i,
                'due_date' => $due,
                'amount' => $amount,
                'remaining' => $amount,
                'status' => 'لم يتم الدفع',
                'user_id' => $userId,
            ]);

            // تحديث المجاميع
            $paidSum = bcadd($paidSum, $amount, 2);
            $lastDate = $due;
            $count = $i;
        }

        /*-------------------------------------------------
        | 5. تحديث بيانات الخطة                           |
        --------------------------------------------------*/
        $planModel->update([
            'end_date' => $lastDate,
            'number_of_installments' => $count,  // العدد الفعلى
        ]);
    }
}

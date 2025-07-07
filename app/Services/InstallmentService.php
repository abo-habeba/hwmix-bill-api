<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use Illuminate\Support\Facades\Auth;

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
    try {
        \Log::info('[InstallmentService] 🚀 بدء إنشاء خطة التقسيط للفـاتورة رقم: ' . $invoiceId, $data);

        // 1. بيانات أساسية
        $planData = $data['installment_plan'];
        $userId = $data['user_id'];
        $startDate = Carbon::parse($planData['start_date']);
        $roundStep = isset($planData['round_step']) && $planData['round_step'] > 0 ? (int)$planData['round_step'] : 10;

        // 2. حساب المبالغ
        $totalAmount = $planData['total_amount'];
        $downPayment = $planData['down_payment'];
        $installmentsN = (int) $planData['number_of_installments'];

        $remaining = bcsub($totalAmount, $downPayment, 2);
        $avgInst = bcdiv($remaining, $installmentsN, 2);
        $ceilTo = static fn(string $val, int $step): string => number_format(ceil((float)$val / $step) * $step, 2, '.', '');
        $stdInst = $ceilTo($avgInst, $roundStep);

        \Log::info('[InstallmentService] 🧮 القسط القياسي بعد التقريب: ' . $stdInst);

        // 3. إنشاء خطة الأقساط
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
            'status' => 'لم يتم الدفع',
            'notes' => $planData['notes'] ?? null,
        ]);

        \Log::info('[InstallmentService] ✅ تم إنشاء خطة الأقساط بنجاح', ['plan_id' => $planModel->id]);

        // 4. التعامل مع الرصيد
        $cashBoxId = $data['cash_box_id'] ?? null;
        $authUser = Auth::user();

        if ($userId && $authUser && $userId == $authUser->id) {
            \Log::info('[InstallmentService] 🤝 عملية بيع لنفسه، تسجيل دين تلقائي');
            app(\App\Services\UserSelfDebtService::class)->registerInstallmentPayment(
                $authUser, $downPayment, $remaining, $cashBoxId, $planModel->company_id ?? null
            );
        } else {
            if ($downPayment > 0 && $authUser) {
                \Log::info('[InstallmentService] 💰 إيداع المقدم للموظف رقم ' . $authUser->id);
                $authUser->deposit($downPayment, $cashBoxId);
            }

            if ($remaining > 0 && $userId) {
                $buyer = \App\Models\User::find($userId);
                if ($buyer) {
                    \Log::info('[InstallmentService] 💸 خصم المتبقي من العميل رقم ' . $buyer->id);
                    $buyer->withdraw($remaining, $cashBoxId);
                }
            }
        }

        // 5. إنشاء الأقساط
        $paidSum = '0.00';
        $count = 0;
        $lastDate = null;

        for ($i = 1; $i <= $installmentsN; $i++) {
            $left = bcsub($remaining, $paidSum, 2);
            if (bccomp($left, '0.00', 2) <= 0)
                break;

            $amount = (bccomp($stdInst, $left, 2) === 1 || $i === $installmentsN) ? $left : $stdInst;
            $due = $startDate->copy()->addMonths($i)->format('Y-m-d');

            Installment::create([
                'installment_plan_id' => $planModel->id,
                'installment_number' => $i,
                'due_date' => $due,
                'amount' => $amount,
                'remaining' => $amount,
                'status' => 'لم يتم الدفع',
                'user_id' => $userId,
            ]);

            \Log::info("[InstallmentService] ➕ تم إنشاء القسط رقم {$i} بقيمة {$amount} وتاريخ {$due}");

            $paidSum = bcadd($paidSum, $amount, 2);
            $lastDate = $due;
            $count = $i;
        }

        // 6. تحديث بيانات الخطة
        $planModel->update([
            'end_date' => $lastDate,
            'number_of_installments' => $count,
        ]);

        \Log::info('[InstallmentService] 🎯 تم تحديث الخطة بالعدد الفعلي للأقساط: ' . $count);

    } catch (\Throwable $e) {
        \Log::error('[InstallmentService] 💥 حصل استثناء أثناء إنشاء خطة التقسيط', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}
}

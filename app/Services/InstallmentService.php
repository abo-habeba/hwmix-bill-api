<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\User; // تم إضافة استيراد لنموذج المستخدم
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // تم إضافة استيراد لـ Log

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
            Log::info('[InstallmentService] بدء إنشاء خطة التقسيط للفاتورة رقم: ' . $invoiceId, $data);

            $planData = $data['installment_plan'];
            $userId = $data['user_id'];
            $startDate = Carbon::parse($planData['start_date']);
            $roundStep = isset($planData['round_step']) && $planData['round_step'] > 0 ? (int)$planData['round_step'] : 10;

            $totalAmount = $planData['total_amount'];
            $downPayment = $planData['down_payment'];
            $installmentsN = (int) $planData['number_of_installments'];

            $remaining = bcsub($totalAmount, $downPayment, 2);
            $avgInst = bcdiv($remaining, $installmentsN, 2);
            $ceilTo = static fn(string $val, int $step): string => number_format(ceil((float)$val / $step) * $step, 2, '.', '');
            $stdInst = $ceilTo($avgInst, $roundStep);

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
                'status' => 'لم يتم الدفع', // الحالة الأولية
                'notes' => $planData['notes'] ?? null,
            ]);
            Log::info('[InstallmentService] تم إنشاء خطة التقسيط.', ['plan_id' => $planModel->id]);

            // هذا الجزء من معالجة الأرصدة تم نقله إلى InstallmentSaleInvoiceService::create
            // لضمان فصل المسؤوليات وعدم تكرار منطق التعامل مع الدفعة الأولى ودين العميل الكلي
            // $cashBoxId = $data['cash_box_id'] ?? null;
            // $authUser = Auth::user();
            // if ($userId && $authUser && $userId == $authUser->id) {
            //     app(UserSelfDebtService::class)->registerInstallmentPayment(
            //         $authUser,
            //         $downPayment,
            //         $remaining,
            //         $cashBoxId,
            //         $planModel->company_id ?? null
            //     );
            // } else {
            //     if ($downPayment > 0 && $authUser) {
            //         $authUser->deposit($downPayment, $cashBoxId);
            //     }
            // }

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
                    'status' => 'لم يتم الدفع', // الحالة الأولية للقسط
                    'user_id' => $userId,
                ]);
                Log::info('[InstallmentService] تم إنشاء قسط.', ['plan_id' => $planModel->id, 'installment_number' => $i, 'amount' => $amount]);

                $paidSum = bcadd($paidSum, $amount, 2);
                $lastDate = $due;
                $count = $i;
            }

            $planModel->update([
                'end_date' => $lastDate,
                'number_of_installments' => $count,
            ]);
            Log::info('[InstallmentService] تم تحديث نهاية خطة التقسيط وعدد الأقساط.', ['plan_id' => $planModel->id]);
        } catch (\Throwable $e) {
            Log::error('[InstallmentService] حصل استثناء أثناء إنشاء خطة التقسيط', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * إلغاء خطة الأقساط والأقساط الفردية المرتبطة بفاتورة.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بخطة الأقساط.
     * @return float إجمالي المبالغ المدفوعة للأقساط الفردية التي تم عكسها.
     * @throws \Throwable
     */
    public function cancelInstallments(Invoice $invoice): float
    {
        Log::info('[InstallmentService] بدء إلغاء الأقساط للفاتورة رقم: ' . $invoice->id);
        $totalPaidAmountFromInstallments = 0;

        if (!$invoice->installmentPlan) {
            Log::warning('[InstallmentService] لا توجد خطة أقساط للفاتورة رقم: ' . $invoice->id);
            return $totalPaidAmountFromInstallments;
        }

        $installmentPlan = $invoice->installmentPlan;
        $buyer = User::find($installmentPlan->user_id); // العميل/المشتري المرتبط بخطة الأقساط

        foreach ($installmentPlan->installments as $installment) {
            // عكس المعاملات المالية للأقساط المدفوعة أو المدفوعة جزئيًا
            if (in_array($installment->status, ['مدفوع', 'مدفوع جزئيًا'])) {
                // افتراض أن payments() هي علاقة للوصول إلى سجلات الدفع للقسط
                // وأن 'amount_paid' هو الحقل الذي يحتوي على المبلغ المدفوع في هذه السجلات.
                $paidAmountForThisInstallment = $installment->payments()->sum('amount_paid');

                if ($paidAmountForThisInstallment > 0) {
                    // يجب أن يكون creator هو الموظف الذي استلم الدفعة
                    $staff = $installment->creator; // افتراض وجود علاقة creator في نموذج Installment

                    // تحديد معرف صندوق النقدية الذي تم الإيداع فيه
                    // هذا قد يكون أكثر تعقيدًا، ويفضل أن يكون مسجلًا مع كل معاملة دفع
                    // هنا سنستخدم cash_box_id من الفاتورة كافتراض، أو يمكن تمريره
                    $cashBoxId = $invoice->cash_box_id ?? null; // قد تحتاج لتحديد هذا بشكل أدق

                    // 1. سحب المبلغ من خزنة الموظف (لأن الموظف استلمه عند الدفع)
                    if ($staff) {
                        $withdrawResult = $staff->withdraw($paidAmountForThisInstallment, $cashBoxId);
                        if ($withdrawResult !== true) {
                            Log::error('[InstallmentService] فشل سحب مبلغ القسط المسترد من خزنة الموظف.', ['staff_id' => $staff->id, 'amount' => $paidAmountForThisInstallment, 'result' => $withdrawResult]);
                            // يمكن رمي استثناء هنا إذا كان الفشل حرجًا
                        } else {
                            Log::info('[InstallmentService] تم سحب مبلغ القسط المسترد من خزنة الموظف.', ['staff_id' => $staff->id, 'amount' => $paidAmountForThisInstallment]);
                        }
                    } else {
                        Log::warning('[InstallmentService] لم يتم العثور على الموظف الذي استلم دفع القسط.', ['installment_id' => $installment->id]);
                    }

                    // 2. إيداع المبلغ في رصيد العميل (لأن العميل يسترد ما دفعه)
                    if ($buyer) {
                        $depositResult = $buyer->deposit($paidAmountForThisInstallment, $cashBoxId); // استخدام نفس cashBoxId أو تحديد cashBoxId للعميل
                        if ($depositResult !== true) {
                            Log::error('[InstallmentService] فشل إيداع مبلغ القسط المسترد في رصيد العميل.', ['buyer_id' => $buyer->id, 'amount' => $paidAmountForThisInstallment, 'result' => $depositResult]);
                            // يمكن رمي استثناء هنا إذا كان الفشل حرجًا
                        } else {
                            Log::info('[InstallmentService] تم إيداع مبلغ القسط المسترد في رصيد العميل.', ['buyer_id' => $buyer->id, 'amount' => $paidAmountForThisInstallment]);
                        }
                    } else {
                        Log::warning('[InstallmentService] لم يتم العثور على العميل لإيداع مبلغ القسط المسترد.', ['installment_id' => $installment->id]);
                    }

                    $totalPaidAmountFromInstallments += $paidAmountForThisInstallment;
                }
            }
            // تغيير حالة القسط إلى ملغاة بدلاً من الحذف
            $installment->update(['status' => 'canceled']);
            Log::info('[InstallmentService] تم إلغاء القسط.', ['installment_id' => $installment->id, 'old_status' => $installment->getOriginal('status')]);
        }

        // تغيير حالة خطة الأقساط إلى ملغاة بدلاً من الحذف
        $installmentPlan->update(['status' => 'canceled']);
        Log::info('[InstallmentService] تم إلغاء خطة الأقساط.', ['installment_plan_id' => $installmentPlan->id, 'old_status' => $installmentPlan->getOriginal('status')]);

        return $totalPaidAmountFromInstallments;
    }
}

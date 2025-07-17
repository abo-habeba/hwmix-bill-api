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
        $customer = User::find($installmentPlan->user_id); // العميل/المشتري المرتبط بخطة الأقساط

        if (!$customer) {
            Log::error('InstallmentService: لم يتم العثور على العميل المرتبط بخطة الأقساط.', ['user_id' => $installmentPlan->user_id]);
            throw new \Exception('InstallmentService: لم يتم العثور على العميل المرتبط بخطة الأقساط.');
        }

        // استرجاع جميع دفعات خطة التقسيط هذه
        $paymentsToReverse = InstallmentPayment::where('installment_plan_id', $installmentPlan->id)->get();

        foreach ($paymentsToReverse as $payment) {
            $paidAmount = $payment->amount_paid; // المبلغ الإجمالي المدفوع في هذه المعاملة
            $staff = User::find($payment->created_by); // الموظف الذي أنشأ سجل الدفعة
            $companyId = $payment->company_id; // الشركة المرتبطة بهذه الدفعة

            // تحديد صندوق النقدية. يمكن افتراض أن `cash_box_id` من الفاتورة الأصلية
            // أو يجب أن يكون هناك `cash_box_id` مسجل في `installment_payments`
            // حاليا، `installment_payments` لا يحتوي على `cash_box_id`، لذا سنستخدم `invoice->cash_box_id` كافتراضي
            $companyCashBoxId = $invoice->cash_box_id;
            // صندوق نقدية العميل (يمكن أن يكون هو نفسه صندوق الشركة إذا لم يتم تتبع أرصدة المستخدمين بخزائن منفصلة)
            $userCashBoxId = $invoice->cash_box_id; // افتراضيا نفسه

            if ($paidAmount > 0) {
                // 1. سحب المبلغ من خزنة الموظف (الذي استلم الدفعة الأصلية)
                if ($staff) {
                    // يجب أن يكون هناك حقل cash_box_id في جدول installment_payments
                    // لتحديد الخزنة التي دخلها المال أصلاً.
                    // في حالتك الحالية، لا يوجد cash_box_id في installment_payments،
                    // لذا سأفترض استخدام cash_box_id من الفاتورة.
                    // هذا قد لا يكون دقيقًا إذا كان الموظفون يستخدمون خزائن مختلفة.
                    $withdrawResult = $staff->withdraw($paidAmount, $companyCashBoxId); // استخدم companyCashBoxId هنا
                    if ($withdrawResult !== true) {
                        Log::error('InstallmentService: فشل سحب مبلغ الدفعة المسترد من خزنة الموظف.', ['staff_id' => $staff->id, 'amount' => $paidAmount, 'result' => $withdrawResult, 'payment_id' => $payment->id]);
                        throw new \Exception('فشل سحب مبلغ الدفعة المسترد من خزنة الموظف.');
                    } else {
                        Log::info('InstallmentService: تم سحب مبلغ الدفعة المسترد من خزنة الموظف.', ['staff_id' => $staff->id, 'amount' => $paidAmount, 'payment_id' => $payment->id]);
                    }
                } else {
                    Log::warning('InstallmentService: لم يتم العثور على الموظف الذي استلم الدفعة.', ['payment_id' => $payment->id]);
                }

                // 2. إيداع المبلغ في رصيد العميل (المشتري) لأنه يسترد ما دفعه
                // هنا نستخدم $customer (نموذج User) الذي هو المشتري/العميل
                $depositResult = $customer->deposit($paidAmount, $userCashBoxId);
                if ($depositResult !== true) {
                    Log::error('InstallmentService: فشل إيداع مبلغ الدفعة المسترد في رصيد العميل.', ['customer_id' => $customer->id, 'amount' => $paidAmount, 'result' => $depositResult, 'payment_id' => $payment->id]);
                    throw new \Exception('فشل إيداع مبلغ الدفعة المسترد في رصيد العميل.');
                } else {
                    Log::info('InstallmentService: تم إيداع مبلغ الدفعة المسترد في رصيد العميل.', ['customer_id' => $customer->id, 'amount' => $paidAmount, 'payment_id' => $payment->id]);
                }

                $totalReversedAmount += $paidAmount;
            }

            // حذف سجل الدفع وتفاصيله، أو تغييره حالته إلى 'reversed'
            // من الأفضل تغيير الحالة بدلاً من الحذف للحفاظ على سجلات التدقيق
            // إذا كان InstallmentPayment لديه حقل 'status'
            // $payment->update(['status' => 'reversed']);
            $payment->delete(); // أو حذف إذا كان هذا هو المطلوب
            Log::info('InstallmentService: تم حذف سجل الدفع الرئيسي.', ['payment_id' => $payment->id]);

            // حذف تفاصيل الدفع المرتبطة
            InstallmentPaymentDetail::where('installment_payment_id', $payment->id)->delete();
            Log::info('InstallmentService: تم حذف تفاصيل الدفع المرتبطة.', ['payment_id' => $payment->id]);
        }

        // تحديث حالة جميع الأقساط التابعة لخطة الأقساط إلى 'canceled'
        $installmentPlan->installments()->update(['status' => 'canceled', 'remaining' => 0, 'paid_at' => null]);
        Log::info('InstallmentService: تم تحديث حالة جميع الأقساط إلى ملغاة.', ['plan_id' => $installmentPlan->id]);

        // تحديث حالة خطة الأقساط إلى 'canceled'
        $installmentPlan->update(['status' => 'canceled', 'remaining_amount' => 0]);
        Log::info('InstallmentService: تم إلغاء خطة الأقساط.', ['installment_plan_id' => $installmentPlan->id]);

        return $totalReversedAmount;
    }
}

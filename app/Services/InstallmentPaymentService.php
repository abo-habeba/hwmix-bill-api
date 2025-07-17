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
use App\Models\PaymentMethod;

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
            $remainingAmountToDistribute = $amount; // المبلغ المتبقي لتوزيعه على الأقساط
            $totalAmountSuccessfullyPaid = 0; // المبلغ الفعلي الذي تم تطبيقه على الأقساط في هذه العملية

            $authUser = Auth::user(); // الموظف الذي يقوم بالعملية (الذي يستلم الدفعة)

            // جلب خطة التقسيط
            $installmentPlan = InstallmentPlan::with('installments')->find($options['installment_plan_id'] ?? null);
            if (!$installmentPlan) {
                throw new Exception('InstallmentPaymentService: خطة التقسيط غير موجودة.');
            }

            // تحديد صندوق النقد للموظف (الذي يستلم الدفعة)
            // استخدام cash_box_id من الـ options أولاً، ثم الافتراضي للموظف
            $cashBoxId = $options['cash_box_id'] ?? $authUser->cashBoxeDefault?->id;
            if (!$cashBoxId) {
                throw new Exception('InstallmentPaymentService: لم يتم تحديد صندوق نقدي للموظف المستلم.');
            }

            // تحديد العميل (الذي يدفع القسط)
            $clientUser = User::find($installmentPlan->user_id);
            if (!$clientUser) {
                throw new Exception('InstallmentPaymentService: لم يتم العثور على العميل المرتبط بخطة التقسيط.');
            }
            // تحديد صندوق النقد للعميل (صندوق النقد الخاص به لتقليل دينه)
            // يمكن أن يكون هو نفسه صندوق الشركة إذا كان نظام أرصدة المستخدمين مركزياً
            $clientCashBoxId = $options['user_cash_box_id'] ?? $clientUser->cashBoxeDefault?->id;
            if (!$clientCashBoxId) {
                // إذا لم يكن للعميل صندوق افتراضي، استخدم نفس صندوق الموظف المستلم كافتراضي لعمليات العميل
                $clientCashBoxId = $cashBoxId;
                Log::warning('InstallmentPaymentService: لم يتم العثور على صندوق نقدي افتراضي للعميل. استخدام صندوق الموظف المستلم كبديل.', ['user_id' => $clientUser->id, 'fallback_cash_box_id' => $clientCashBoxId]);
            }


            // تحديد اسم طريقة الدفع بناءً على payment_method_id
            $paymentMethodName = 'نقداً'; // قيمة افتراضية منطقية
            if (isset($options['payment_method_id'])) {
                $paymentMethod = PaymentMethod::find($options['payment_method_id']);
                if ($paymentMethod) {
                    $paymentMethodName = $paymentMethod->name;
                }
            }

            // إنشاء سجل الدفعة الرئيسية
            $installmentPayment = InstallmentPayment::create([
                'installment_plan_id' => $installmentPlan->id,
                'company_id' => $installmentPlan->company_id,
                'created_by' => $authUser->id,
                'payment_date' => $options['paid_at'] ?? now(),
                'amount_paid' => 0, // سيبدأ بصفر وسيتم تحديثه بالمبلغ الفعلي الذي تم تطبيقه
                'payment_method' => $paymentMethodName,
                'notes' => $options['notes'] ?? '',
                'cash_box_id' => $cashBoxId, // ✅ حفظ صندوق النقد الذي استلم الدفعة
            ]);
            Log::info('InstallmentPaymentService: تم إنشاء سجل الدفعة الرئيسي.', ['payment_id' => $installmentPayment->id, 'initial_amount' => $amount]);

            // جلب الأقساط المراد دفعها (مرتبة حسب تاريخ الاستحقاق)
            // هذا الترتيب يضمن دفع الأقساط الأقدم أولاً
            $installments = $installmentPlan->installments()
                ->whereIn('id', $installmentIds) // الأقساط المحددة أولاً
                ->where('status', '!=', 'canceled')
                ->where('remaining', '>', 0)
                ->orderBy('due_date')
                ->get();

            // إذا لم يتم تحديد أقساط، أو كانت جميعها مدفوعة، ابحث عن أقساط أخرى مستحقة
            if ($installments->isEmpty()) {
                $installments = $installmentPlan->installments()
                    ->where('status', '!=', 'canceled')
                    ->where('remaining', '>', 0)
                    ->orderBy('due_date')
                    ->get();
            }

            foreach ($installments as $installment) {
                if ($remainingAmountToDistribute <= 0) {
                    break; // لا يوجد المزيد من المبلغ لتوزيعه
                }

                $amountToApplyToCurrentInstallment = min($remainingAmountToDistribute, $installment->remaining);
                $newRemaining = bcsub($installment->remaining, $amountToApplyToCurrentInstallment, 2);
                $newStatus = $installment->status;

                if (bccomp($newRemaining, '0.00', 2) <= 0) {
                    $newStatus = 'paid'; // تم الدفع بالكامل
                } elseif (bccomp((string)$amountToApplyToCurrentInstallment, '0.00', 2) > 0 && bccomp($newRemaining, '0.00', 2) > 0) {
                    $newStatus = 'partially_paid'; // مدفوع جزئيًا
                }

                // تحديث القسط الفردي
                $installment->update([
                    'remaining' => $newRemaining,
                    'status' => $newStatus,
                    'paid_at' => ($newStatus === 'paid' && !$installment->paid_at) ? ($options['paid_at'] ?? now()) : $installment->paid_at,
                ]);

                // تسجيل تفاصيل الدفع لهذا القسط
                InstallmentPaymentDetail::create([
                    'installment_payment_id' => $installmentPayment->id,
                    'installment_id' => $installment->id,
                    'amount_paid' => $amountToApplyToCurrentInstallment,
                ]);

                $remainingAmountToDistribute = bcsub($remainingAmountToDistribute, $amountToApplyToCurrentInstallment, 2);
                $totalAmountSuccessfullyPaid = bcadd($totalAmountSuccessfullyPaid, $amountToApplyToCurrentInstallment, 2);

                Log::info('InstallmentPaymentService: تم تطبيق دفعة على قسط.', [
                    'installment_id' => $installment->id,
                    'amount_applied' => $amountToApplyToCurrentInstallment,
                    'new_remaining' => $newRemaining,
                    'new_status' => $newStatus
                ]);
            }

            // تحديث المبلغ المدفوع الفعلي في سجل الدفعة الرئيسية
            $installmentPayment->update(['amount_paid' => $totalAmountSuccessfullyPaid]);
            Log::info('InstallmentPaymentService: تم تحديث المبلغ المدفوع الكلي في سجل الدفعة الرئيسية.', ['payment_id' => $installmentPayment->id, 'actual_paid' => $totalAmountSuccessfullyPaid]);


            // تحديث حالة خطة الأقساط بناءً على حالة الأقساط الفردية
            $this->updateInstallmentPlanStatus($installmentPlan);
            Log::info('InstallmentPaymentService: تم تحديث حالة خطة التقسيط.', ['plan_id' => $installmentPlan->id, 'new_status' => $installmentPlan->status]);


            // 1. إيداع المبلغ في خزنة الموظف (الذي استلم الدفعة)
            $depositResultStaff = $authUser->deposit($totalAmountSuccessfullyPaid, $cashBoxId);
            if ($depositResultStaff !== true) {
                throw new Exception('InstallmentPaymentService: فشل إيداع المبلغ في خزنة الموظف: ' . json_encode($depositResultStaff));
            }
            Log::info('InstallmentPaymentService: تم إيداع المبلغ في خزنة الموظف.', [
                'user_id' => $authUser->id,
                'cash_box_id' => $cashBoxId,
                'amount' => $totalAmountSuccessfullyPaid
            ]);

            // 2. خصم المبلغ من رصيد العميل (لتسجيل الدفع وتقليل دينه)
            // لاحظ أن `deposit` هنا يتم استدعاؤه على `clientUser` لزيادة رصيده،
            // وهذا منطقي إذا كان رصيد العميل يمثل دينه بالسالب، أي زيادة الرصيد تقلل الدين.
            // إذا كان رصيد العميل يمثل أمواله، فيجب استخدام `withdraw` هنا لخفض رصيده لأنه دفع المال.
            // بناءً على سياق "تقليل الدين"، نفترض أن `deposit` هو الإجراء الصحيح الذي يعكس تخفيض المديونية.
            // ولكن للتوضيح، إذا كان رصيد العميل يعني أمواله المتاحة، فسيحتاج إلى `withdraw` بدلاً من `deposit`
            // لتمثيل خروج المال منه.
            // **إذا كان رصيد العميل يعبر عن مديونيته (الأرقام السالبة تعبر عن الدين)، فإن `deposit` ستجعل الرقم أقل سالبية أو إيجابياً، وهذا يمثل سداد الدين.**
            // **إذا كان رصيد العميل يعبر عن أمواله المتاحة، فإن سداد الدين يتطلب `withdraw` منه.**
            // بالنظر إلى أنك تستخدم `deposit`، سنفترض أن رصيد العميل يتصرف كمديونية تنخفض عند الدفع (أي يصبح أقل سالبية).
            // في سياق دفع قسط، العميل يدفع، لذا المال يخرج منه.
            // *تصحيح محتمل*: إذا كان `$clientUser->deposit` يزيد رصيد العميل (أي أمواله)،
            // فإن العملية الصحيحة لتقليل دين العميل (خروج المال منه) هي `$clientUser->withdraw`.
            // يرجى التأكد من سلوك دالتي `deposit` و `withdraw` على نموذج `User` بالنسبة لأرصدة العملاء.
            // سأفترض هنا أن `deposit` على العميل يعني تقليل مديونيته (وهو أمر شائع في أنظمة الديون).
            $depositResultClient = $clientUser->deposit($totalAmountSuccessfullyPaid, $clientCashBoxId);
            if ($depositResultClient !== true) {
                throw new Exception('InstallmentPaymentService: فشل تحديث رصيد العميل (تقليل الدين): ' . json_encode($depositResultClient));
            }
            Log::info('InstallmentPaymentService: تم تحديث رصيد العميل (تقليل الدين).', [
                'user_id' => $clientUser->id,
                'cash_box_id' => $clientCashBoxId,
                'amount' => $totalAmountSuccessfullyPaid
            ]);

            DB::commit();
            Log::info('InstallmentPaymentService: تمت عملية دفع الأقساط بنجاح.', ['installment_payment_id' => $installmentPayment->id]);

            // إضافة خاصية ديناميكية للكائن للإشارة إلى المبلغ الزائد
            if (bccomp($remainingAmountToDistribute, '0.00', 2) > 0) {
                $installmentPayment->excess_amount = $remainingAmountToDistribute;
                Log::info('InstallmentPaymentService: تم دفع جميع الأقساط وبقي مبلغ زائد.', [
                    'installment_plan_id' => $installmentPlan->id,
                    'excess_amount' => $remainingAmountToDistribute
                ]);
            }

            return $installmentPayment;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('InstallmentPaymentService: فشل في دفع الأقساط.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'installment_ids' => $installmentIds,
                'amount_attempted' => $amount
            ]);
            throw $e;
        }
    }

    /**
     * تحديث حالة خطة الأقساط بناءً على حالة الأقساط الفردية.
     *
     * @param InstallmentPlan $installmentPlan
     * @return void
     */
    protected function updateInstallmentPlanStatus(InstallmentPlan $installmentPlan): void
    {
        $totalInstallments = $installmentPlan->installments->count();
        $paidInstallments = $installmentPlan->installments->where('status', 'paid')->count();
        $partiallyPaidInstallments = $installmentPlan->installments->where('status', 'partially_paid')->count();
        $canceledInstallments = $installmentPlan->installments->where('status', 'canceled')->count();

        // تحديث إجمالي المبلغ المتبقي لخطة الأقساط
        $newRemainingAmountPlan = $installmentPlan->installments->sum('remaining');
        $installmentPlan->update(['remaining_amount' => $newRemainingAmountPlan]);

        if ($paidInstallments === ($totalInstallments - $canceledInstallments)) {
            // إذا تم دفع جميع الأقساط (باستثناء الملغاة)
            $installmentPlan->update(['status' => 'paid']);
        } elseif ($paidInstallments > 0 || $partiallyPaidInstallments > 0) {
            // إذا تم دفع أي قسط كليًا أو جزئيًا
            $installmentPlan->update(['status' => 'partially_paid']);
        } else {
            // إذا لم يتم دفع أي شيء
            $installmentPlan->update(['status' => 'pending']);
        }
    }
}

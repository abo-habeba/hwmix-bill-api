<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use Illuminate\Support\Facades\Auth;

class InstallmentService
{
    public function createInstallments(array $data, int $invoiceId): void
    {
        try {
            \Log::info('[InstallmentService] \uD83D\uDE80 بدء إنشاء خطة التقسيط للفـاتورة رقم: ' . $invoiceId, $data);

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
                'status' => 'لم يتم الدفع',
                'notes' => $planData['notes'] ?? null,
            ]);

            $cashBoxId = $data['cash_box_id'] ?? null;
            $authUser = Auth::user();

            if ($userId && $authUser && $userId == $authUser->id) {
                app(UserSelfDebtService::class)->registerInstallmentPayment(
                    $authUser,
                    $downPayment,
                    $remaining,
                    $cashBoxId,
                    $planModel->company_id ?? null
                );
            } else {
                if ($downPayment > 0 && $authUser) {
                    $authUser->deposit($downPayment, $cashBoxId);
                }
                // if ($remaining > 0 && $userId) {
                //     $buyer = \App\Models\User::find($userId);
                //     if ($buyer) {
                //         $buyer->withdraw($remaining, $cashBoxId);
                //     }
                // }
            }

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

                $paidSum = bcadd($paidSum, $amount, 2);
                $lastDate = $due;
                $count = $i;
            }

            $planModel->update([
                'end_date' => $lastDate,
                'number_of_installments' => $count,
            ]);
        } catch (\Throwable $e) {
            \Log::error('[InstallmentService] \uD83D\uDCA5 حصل استثناء أثناء إنشاء خطة التقسيط', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function cancelInstallments(Invoice $invoice): void
    {
        if (!$invoice->installmentPlan) return;

        foreach ($invoice->installmentPlan->installments as $installment) {
            if (in_array($installment->status, ['مدفوع', 'مدفوع جزئيًا'])) {
                $totalPaid = $installment->payments()->sum('installment_payment_details.amount_paid');
                $staff = $installment->creator;
                if ($staff && $totalPaid > 0) {
                    $staff->withdraw($totalPaid, $invoice->cash_box_id ?? null);
                }
            }
            $installment->delete();
        }

        $invoice->installmentPlan->delete();
    }
}

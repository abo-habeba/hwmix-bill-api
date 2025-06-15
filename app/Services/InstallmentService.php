<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\InstallmentPlan;
use Carbon\Carbon;

class InstallmentService
{
    /**
     * Create installment plan and installments.
     *
     * @param array $data
     * @param int $invoiceId
     * @return void
     */
    public function createInstallments(array $data, int $invoiceId): void
    {
        $installmentPlanData = $data['installment_plan'];

        // التأكد من تحويل number_of_installments إلى integer
        $numberOfInstallments = (int) $installmentPlanData['number_of_installments'];

        // تحليل start_date مرة واحدة وتحويلها للتنسيق المطلوب
        $parsedStartDate = Carbon::parse($installmentPlanData['start_date']);
        $formattedStartDate = $parsedStartDate->format('Y-m-d H:i:s'); // تنسيق MySQL DATETIME

        // إنشاء خطة الأقساط
        $installmentPlan = InstallmentPlan::create([
            'invoice_id' => $invoiceId,
            'user_id' => $data['user_id'],
            'total_amount' => $installmentPlanData['total_amount'],
            'down_payment' => $installmentPlanData['down_payment'],
            'remaining_amount' => $installmentPlanData['total_amount'] - $installmentPlanData['down_payment'],
            'number_of_installments' => $numberOfInstallments,
            'installment_amount' => $installmentPlanData['installment_amount'],
            'start_date' => $formattedStartDate, // استخدام التاريخ بالتنسيق الصحيح
            'end_date' => $parsedStartDate->copy()->addMonths($numberOfInstallments)->format('Y-m-d H:i:s'), // استخدام copy للحفاظ على التاريخ الأصلي ثم تحويله
            'status' => 'pending',
            'notes' => $installmentPlanData['notes'] ?? null,
        ]);

        // إنشاء الأقساط الفردية
        for ($i = 1; $i <= $numberOfInstallments; $i++) {
            Installment::create([
                'installment_plan_id' => $installmentPlan->id,
                'installment_number' => $i,
                // تحويل تاريخ الاستحقاق إلى التنسيق الصحيح قبل الحفظ
                'due_date' => $parsedStartDate->copy()->addMonths($i)->format('Y-m-d H:i:s'),
                'amount' => $installmentPlanData['installment_amount'],
                'status' => 'pending',
                'user_id' => $data['user_id'],
            ]);
        }
    }
}

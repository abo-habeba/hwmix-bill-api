<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\ActivityLog;
use App\Services\DocumentServiceInterface;

class FinancialTransactionService implements DocumentServiceInterface
{
    // Define methods and properties for financial transactions

    public function create(array $data)
    {
        // إنشاء سند قبض أو صرف
        $transaction = Transaction::create([
            'type' => $data['invoice_type_code'],
            'amount' => $data['total_amount'],
            'status' => $data['status'],
            'company_id' => $data['company_id'],
            'created_by' => $data['created_by'],
        ]);

        // تسجيل سجل النشاط
        ActivityLog::create([
            'action' => 'إنشاء معاملة مالية',
            'user_id' => $data['created_by'],
            'details' => 'تم إنشاء معاملة مالية بقيمة ' . $transaction->amount,
        ]);

        // إعادة البيانات النهائية
        return [
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
        ];
    }
}

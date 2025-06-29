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
            'user_id' => $data['user_id'] ?? null,
            'cashbox_id' => $data['cashbox_id'] ?? null,
            'target_user_id' => $data['target_user_id'] ?? null,
            'target_cashbox_id' => $data['target_cashbox_id'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'type' => $data['invoice_type_code_ar'] ?? $data['type_ar'] ?? $data['invoice_type_code'] ?? $data['type'] ?? 'سند قبض', // افتراضياً سند قبض
            'amount' => $data['total_amount'] ?? $data['amount'] ?? null,
            'balance_before' => $data['balance_before'] ?? null,
            'balance_after' => $data['balance_after'] ?? null,
            'description' => $data['description'] ?? null,
            'original_transaction_id' => $data['original_transaction_id'] ?? null,
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

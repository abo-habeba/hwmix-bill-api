<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\Order;
use App\Models\ActivityLog;
use App\Services\DocumentServiceInterface;

class OrderAndQuotationService implements DocumentServiceInterface
{
    // Define methods and properties for managing orders and quotations

    public function create(array $data)
    {
        // إنشاء عرض سعر أو طلب بناءً على نوع المستند
        $document = match ($data['invoice_type_code']) {
            'quotation' => Quotation::create([
                'invoice_number' => $data['invoice_number'],
                'total_amount' => $data['total_amount'],
                'status' => $data['status'],
                'company_id' => $data['company_id'],
                'created_by' => $data['created_by'],
            ]),
            'sales_order', 'purchase_order' => Order::create([
                'invoice_number' => $data['invoice_number'],
                'total_amount' => $data['total_amount'],
                'status' => $data['status'],
                'company_id' => $data['company_id'],
                'created_by' => $data['created_by'],
            ]),
            default => throw new \Exception('Invalid document type'),
        };

        // تسجيل سجل النشاط
        ActivityLog::create([
            'action' => 'إنشاء مستند',
            'user_id' => $data['created_by'],
            'details' => 'تم إنشاء المستند رقم ' . $document->invoice_number,
        ]);

        // إعادة البيانات النهائية
        return [
            'invoice_number' => $document->invoice_number,
            'total' => $document->total_amount,
        ];
    }
}

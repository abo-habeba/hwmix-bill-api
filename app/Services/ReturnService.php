<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Services\DocumentServiceInterface;

class ReturnService implements DocumentServiceInterface
{
    // Define methods and properties for handling returns

    public function create(array $data)
    {
        // التحقق من البنود وإعادة الكمية إلى المخزون
        foreach ($data['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $product->increment('stock', $item['quantity']);
        }

        // تسجيل سجل النشاط
        ActivityLog::create([
            'action' => 'إرجاع فاتورة',
            'user_id' => $data['created_by'],
            'details' => 'تم إرجاع الفاتورة رقم ' . $data['invoice_number'],
        ]);

        // إعادة البيانات النهائية
        return [
            'invoice_number' => $data['invoice_number'],
            'items' => $data['items'],
        ];
    }
}

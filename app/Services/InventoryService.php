<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Stock;
use App\Services\DocumentServiceInterface;

class InventoryService implements DocumentServiceInterface
{
    // Define methods and properties for inventory management

    public function create(array $data)
    {
        // تعديل أو نقل المخزون بين المخازن
        foreach ($data['items'] as $item) {
            $stock = Stock::findOrFail($item['stock_id']);
            $stock->update([
                'quantity' => $item['quantity'],
            ]);
        }

        // تسجيل سجل النشاط
        ActivityLog::create([
            'action' => 'تعديل المخزون',
            'user_id' => $data['created_by'],
            'details' => 'تم تعديل المخزون بواسطة المستخدم ' . $data['created_by'],
        ]);

        // إعادة البيانات النهائية
        return [
            'status' => 'success',
            'message' => 'تم تعديل المخزون بنجاح',
        ];
    }
}

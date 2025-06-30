<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\DocumentServiceInterface;

class PurchaseInvoiceService implements DocumentServiceInterface
{
    public function create(array $data)
    {
        // التحقق من المنتجات
        foreach ($data['items'] as $item) {
            $variant = ProductVariant::find($item['variant_id']);
            if (!$variant) {
                throw ValidationException::withMessages([
                    'variant_id' => ['المتغير بمعرف ' . $item['variant_id'] . ' غير موجود.'],
                ]);
            }
        }

        // إزالة المفتاح invoice_number من البيانات
        unset($data['invoice_number']);

        // إنشاء الفاتورة
        $invoice = Invoice::create([
            'invoice_type_id' => $data['invoice_type_id'],
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'confirmed',
            'user_id' => $data['user_id'],
            'total_amount' => $data['total_amount'],
            'company_id' => $data['company_id'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        // إنشاء البنود وزيادة الكمية في المخزون
        foreach ($data['items'] as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'total' => $item['total'],
                'company_id' => $data['company_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            // زيادة الكمية في المخزون (أضف في أول مخزون متاح أو أنشئ جديد)
            $currentVariant = ProductVariant::find($item['variant_id']);
            $stock = $currentVariant->stocks()->where('status', 'available')->orderBy('created_at', 'desc')->first();
            if ($stock) {
                $stock->increment('quantity', $item['quantity']);
            } else {
                Stock::create([
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'status' => 'available',
                    'company_id' => $data['company_id'] ?? null,
                    'created_by' => $data['created_by'] ?? null,
                ]);
            }
        }

        // تسجيل سجل النشاط
        $invoice->logCreated('إنشاء فاتورة شراء رقم ' . $invoice->invoice_number);

        // خصم الرصيد من المستخدم عند إنشاء فاتورة شراء
        $authUser = Auth::user();
        $authUser->withdraw($invoice->total_amount);

        return $invoice;
    }
}

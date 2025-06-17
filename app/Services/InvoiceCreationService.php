<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class InvoiceCreationService implements DocumentServiceInterface
{
    // Define methods and properties for invoice creation logic

    public function create(array $data)
    {
        // التحقق من المنتجات والكميات
        foreach ($data['items'] as $item) {
            $variant = ProductVariant::find($item['variant_id']);
            if (!$variant) {
                throw ValidationException::withMessages([
                    'variant_id' => ['المتغير بمعرف ' . $item['variant_id'] . ' غير موجود.'],
                ]);
            }
            $totalAvailableQty = $variant->stocks()->where('status', 'available')->sum('qty');

            if ($totalAvailableQty < $item['quantity']) {
                throw ValidationException::withMessages([
                    'stock' => ['الكمية غير متوفرة في المخزون'],
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
            // 'notes' => $data['notes'],
            // 'installment_plan_id' => $data['installment_plan_id'],
            // 'company_id' => $data['company_id'],
            // 'created_by' => $data['created_by'],
        ]);

        // إنشاء البنود
        foreach ($data['items'] as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'total' => $item['total'],
            ]);

            // خصم الكمية من المخزون
            $currentVariant = ProductVariant::find($item['variant_id']);
            $remainingQuantityToDeduct = $item['quantity'];

            $availableStocks = $currentVariant
                ->stocks()
                ->where('status', 'available')
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($availableStocks as $stock) {
                if ($remainingQuantityToDeduct <= 0) {
                    break;
                }

                $deductQty = min($remainingQuantityToDeduct, $stock->qty);

                if ($deductQty > 0) {
                    $stock->decrement('qty', $deductQty);
                    $remainingQuantityToDeduct -= $deductQty;
                }
            }
        }
        // تسجيل سجل النشاط باستخدام التريت
        $invoice->logCreated('إنشاء فاتورة رقم ' . $invoice->invoice_number);

        // التحقق من نوع الفاتورة وتطبيق منطق الأقساط إذا كانت installment_sale
        if ($data['invoice_type_code'] === 'installment_sale' && isset($data['installment_plan'])) {
            $installmentService = new \App\Services\InstallmentService();
            $installmentService->createInstallments($data, $invoice->id);
        }
        // زيادة رصيد المستخدم عند إنشاء فاتورة بيع
        if ($data['invoice_type_code'] === 'sale') {
            $authUser = auth()->user();
            $authUser->deposit($invoice->total_amount);
        }

        // إعادة البيانات النهائية
        return [
            'invoice_number' => $invoice->invoice_number,
            'total' => $invoice->total_amount,
            'items' => $data['items'],
        ];
    }
}

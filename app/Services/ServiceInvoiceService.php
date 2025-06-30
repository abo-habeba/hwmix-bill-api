<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;

class ServiceInvoiceService implements DocumentServiceInterface
{
    public function create(array $data)
    {
        // لا يوجد تعامل مع المخزون، فقط تسجيل الفاتورة والبنود
        unset($data['invoice_number']);
        $invoice = Invoice::create([
            'invoice_type_id' => $data['invoice_type_id'],
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'confirmed',
            'user_id' => $data['user_id'],
            'total_amount' => $data['total_amount'],
            'company_id' => $data['company_id'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
        foreach ($data['items'] as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $item['product_id'] ?? null,
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'total' => $item['total'],
                'company_id' => $data['company_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
        }
        $invoice->logCreated('إنشاء فاتورة خدمة رقم ' . $invoice->invoice_number);
        $authUser = Auth::user();
        $authUser->deposit($invoice->total_amount);
        return $invoice;
    }
}

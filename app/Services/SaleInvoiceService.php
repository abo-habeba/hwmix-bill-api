<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;

class SaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    public function create(array $data)
    {
        $this->checkVariantsStock($data['items']);
        $invoice = $this->createInvoice($data);
        $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
        // خصم الكمية من المخزون
        $this->deductStockForItems($data['items']);
        $invoice->logCreated('إنشاء فاتورة بيع رقم ' . $invoice->invoice_number);
        $authUser = Auth::user();
        $authUser->deposit($invoice->total_amount);
        return $invoice;
    }
}

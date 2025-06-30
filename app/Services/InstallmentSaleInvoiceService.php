<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;

class InstallmentSaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    public function create(array $data)
    {
        $this->checkVariantsStock($data['items']);
        $invoice = $this->createInvoice($data);
        $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
        // خصم الكمية من المخزون
        $this->deductStockForItems($data['items']);
        // إنشاء خطة الأقساط
        if (isset($data['installment_plan'])) {
            $installmentService = new \App\Services\InstallmentService();
            $installmentService->createInstallments($data, $invoice->id);
        }
        $invoice->logCreated('إنشاء فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);
        $authUser = Auth::user();
        $authUser->deposit($invoice->total_amount);
        return $invoice;
    }
}

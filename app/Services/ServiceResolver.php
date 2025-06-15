<?php

namespace App\Services;

use App\Exceptions\InvalidInvoiceTypeCodeException;
use App\Services\DocumentServiceInterface;
use App\Services\InvoiceCreationService;
use App\Services\ReturnService;
use App\Services\OrderAndQuotationService;
use App\Services\InventoryService;
use App\Services\FinancialTransactionService;

class ServiceResolver
{
    public static function resolve(string $invoiceTypeCode): DocumentServiceInterface
    {
        return match ($invoiceTypeCode) {
            'sale', 'purchase', 'installment_sale', 'service_invoice', 'discount_invoice' => new InvoiceCreationService(),
            'sale_return', 'purchase_return' => new ReturnService(),
            'quotation', 'sales_order', 'purchase_order' => new OrderAndQuotationService(),
            'inventory_adjustment', 'stock_transfer' => new InventoryService(),
            'receipt', 'payment', 'credit_note', 'debit_note' => new FinancialTransactionService(),
            default => throw new InvalidInvoiceTypeCodeException('Invalid invoice type code: ' . $invoiceTypeCode),
        };
    }
}

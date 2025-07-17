<?php

namespace App\Services;

use App\Exceptions\InvalidInvoiceTypeCodeException;
use App\Services\DocumentServiceInterface;
// ... (باقي الاستيرادات)

class ServiceResolver
{
    public static function resolve(string $invoiceTypeCode): DocumentServiceInterface
    {
        return match ($invoiceTypeCode) {
            // Main invoice types
            'sale' => app(SaleInvoiceService::class),
            'purchase' => app(PurchaseInvoiceService::class),
            'installment_sale' => app(InstallmentSaleInvoiceService::class),
            'service_invoice' => app(ServiceInvoiceService::class),
            'discount_invoice' => app(DiscountInvoiceService::class),

            // Returns
            'sale_return' => app(ReturnService::class),
            'purchase_return' => app(ReturnService::class),

            // Orders & Quotations
            'quotation' => app(OrderAndQuotationService::class),
            'sales_order' => app(OrderAndQuotationService::class),
            'purchase_order' => app(OrderAndQuotationService::class),

            // Inventory
            'inventory_adjustment' => app(InventoryService::class),
            'stock_transfer' => app(InventoryService::class),

            // Financial transactions
            'receipt' => app(FinancialTransactionService::class),
            'payment' => app(FinancialTransactionService::class),
            'credit_note' => app(FinancialTransactionService::class),
            'debit_note' => app(FinancialTransactionService::class),

            default => throw new InvalidInvoiceTypeCodeException('Invalid invoice type code: ' . $invoiceTypeCode),
        };
    }
}

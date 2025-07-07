<?php

namespace App\Services;

use App\Exceptions\InvalidInvoiceTypeCodeException;
use App\Services\DocumentServiceInterface;
use App\Services\InvoiceCreationService;
use App\Services\ReturnService;
use App\Services\OrderAndQuotationService;
use App\Services\InventoryService;
use App\Services\FinancialTransactionService;

/**
 * Resolves the appropriate service for each invoice type code.
 *
 * To add a new invoice type, simply add a new case in the match statement below.
 * If a service requires dependencies, consider refactoring to use the service container.
 */
class ServiceResolver
{
    /**
     * Resolve the service for a given invoice type code.
     *
     * @param string $invoiceTypeCode
     * @return DocumentServiceInterface
     * @throws InvalidInvoiceTypeCodeException
     */
    public static function resolve(string $invoiceTypeCode): DocumentServiceInterface
    {
        return match ($invoiceTypeCode) {
            // Main invoice types
            'sale' => app(SaleInvoiceService::class),
            'purchase' => new PurchaseInvoiceService(),
            'installment_sale' => new InstallmentSaleInvoiceService(),
            'service_invoice' => new ServiceInvoiceService(),
            'discount_invoice' => new DiscountInvoiceService(),

            // Returns
            'sale_return' => new ReturnService(),
            'purchase_return' => new ReturnService(),

            // Orders & Quotations
            'quotation' => new OrderAndQuotationService(),
            'sales_order' => new OrderAndQuotationService(),
            'purchase_order' => new OrderAndQuotationService(),

            // Inventory
            'inventory_adjustment' => new InventoryService(),
            'stock_transfer' => new InventoryService(),

            // Financial transactions
            'receipt' => new FinancialTransactionService(),
            'payment' => new FinancialTransactionService(),
            'credit_note' => new FinancialTransactionService(),
            'debit_note' => new FinancialTransactionService(),

            // Add new types above this line
            default => throw new InvalidInvoiceTypeCodeException('Invalid invoice type code: ' . $invoiceTypeCode),
        };
    }
}

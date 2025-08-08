<?php

namespace App\Services\Financial;

use App\Models\Invoice;
use App\Models\Payment;

interface FinancialServiceInterface
{
    /**
     * يحسب الربح التقديري للفاتورة ويحدد حالتها والمبالغ المتبقية.
     * (خاص بفواتير المبيعات حيث يوجد ربح)
     *
     * @param array $invoiceData بيانات الفاتورة (يجب أن تحتوي على 'items' و 'net_amount' و 'paid_amount').
     * @return array بيانات الفاتورة المحدثة مع 'estimated_profit', 'status', 'remaining_amount'.
     */
    public function calculateInvoiceFinancials(array $invoiceData): array;

    /**
     * ينشئ المعاملات المالية لفاتورة المبيعات (قيود الاستحقاق).
     *
     * @param Invoice $invoice الفاتورة التي تم إنشاؤها/تحديثها.
     * @param array $data البيانات الأصلية للفاتورة.
     * @param int $companyId معرف الشركة.
     * @param ?int $userId معرف المستخدم.
     * @param int $createdBy معرف المستخدم المنشئ/المحدث.
     * @return void
     * @throws \Throwable
     */
    public function createInvoiceFinancialTransactions(Invoice $invoice, array $data, int $companyId, ?int $userId, int $createdBy): void;

    /**
     * يحدث المعاملات المالية لفاتورة المبيعات (قيود الاستحقاق).
     *
     * @param Invoice $invoice الفاتورة التي تم تحديثها.
     * @param array $data البيانات الجديدة للفاتورة.
     * @param int $companyId معرف الشركة.
     * @param ?int $userId معرف المستخدم.
     * @param int $updatedBy معرف المستخدم المحدث.
     * @return void
     * @throws \Throwable
     */
    public function updateInvoiceFinancialTransactions(Invoice $invoice, array $data, int $companyId, ?int $userId, int $updatedBy): void;

    /**
     * يعكس/يحذف المعاملات المالية المرتبطة بأي فاتورة (سواء بيع أو شراء).
     *
     * @param Invoice $invoice الفاتورة المراد عكس معاملاتها.
     * @return void
     * @throws \Throwable
     */
    public function reverseInvoiceFinancialTransactions(Invoice $invoice): void;

    /**
     * يعالج الدفعة الأولية أو الإضافية لفاتورة المبيعات وينشئ المعاملات المالية والدفعات.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بالدفعة.
     * @param float $amount مبلغ الدفعة.
     * @param int $cashBoxId معرف الصندوق النقدي.
     * @param int $companyId معرف الشركة.
     * @param ?int $userId معرف المستخدم (العميل).
     * @param int $createdBy معرف المستخدم المنشئ.
     * @return Payment الدفعة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function handleInvoicePayment(Invoice $invoice, float $amount, int $cashBoxId, int $companyId, ?int $userId, int $createdBy): Payment;

    /**
     * يعكس/يحذف الدفعات والمعاملات المالية المرتبطة بأي فاتورة (سواء بيع أو شراء).
     *
     * @param Invoice $invoice الفاتورة المرتبطة بالدفعات.
     * @return void
     * @throws \Throwable
     */
    public function reverseInvoicePayments(Invoice $invoice): void;

    // --------------------------------------------------------------------
    // طرق جديدة خاصة بفواتير الشراء
    // --------------------------------------------------------------------

    /**
     * ينشئ المعاملات المالية لفاتورة الشراء (قيود الاستحقاق).
     *
     * @param Invoice $invoice الفاتورة التي تم إنشاؤها/تحديثها.
     * @param array $data البيانات الأصلية للفاتورة.
     * @param int $companyId معرف الشركة.
     * @param ?int $userId معرف المستخدم (المورد).
     * @param int $createdBy معرف المستخدم المنشئ/المحدث.
     * @return void
     * @throws \Throwable
     */
    public function createPurchaseFinancialTransactions(Invoice $invoice, array $data, int $companyId, ?int $userId, int $createdBy): void;

    /**
     * يحدث المعاملات المالية لفاتورة الشراء (قيود الاستحقاق).
     *
     * @param Invoice $invoice الفاتورة التي تم تحديثها.
     * @param array $data البيانات الجديدة للفاتورة.
     * @param int $companyId معرف الشركة.
     * @param ?int $userId معرف المستخدم (المورد).
     * @param int $updatedBy معرف المستخدم المحدث.
     * @return void
     * @throws \Throwable
     */
    public function updatePurchaseFinancialTransactions(Invoice $invoice, array $data, int $companyId, ?int $userId, int $updatedBy): void;

    /**
     * يعالج الدفعة الأولية أو الإضافية لفاتورة الشراء (دفعة صادرة) وينشئ المعاملات المالية والدفعات.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بالدفعة.
     * @param float $amount مبلغ الدفعة.
     * @param int $cashBoxId معرف الصندوق النقدي.
     * @param int $companyId معرف الشركة.
     * @param ?int $userId معرف المستخدم (المورد).
     * @param int $createdBy معرف المستخدم المنشئ.
     * @return Payment الدفعة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function handlePurchasePayment(Invoice $invoice, float $amount, int $cashBoxId, int $companyId, ?int $userId, int $createdBy): Payment;

    /**
     * يعكس/يحذف المعاملات المالية المرتبطة بفاتورة الشراء.
     *
     * @param Invoice $invoice الفاتورة المراد عكس معاملاتها.
     * @return void
     * @throws \Throwable
     */
    public function reversePurchaseFinancialTransactions(Invoice $invoice): void;

    /**
     * يعكس/يحذف الدفعات والمعاملات المالية المرتبطة بفاتورة الشراء.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بالدفعات.
     * @return void
     * @throws \Throwable
     */
    public function reversePurchasePayments(Invoice $invoice): void;

    // --------------------------------------------------------------------
    // طرق جديدة خاصة بفواتير البيع بالتقسيط
    // --------------------------------------------------------------------

    /**
     * ينشئ المعاملات المالية لفاتورة البيع بالتقسيط (قيود الاستحقاق).
     *
     * @param Invoice $invoice الفاتورة التي تم إنشاؤها/تحديثها.
     * @param array $data البيانات الأصلية للفاتورة.
     * @param int $companyId معرف الشركة.
     * @param ?int $userId معرف المستخدم (العميل).
     * @param int $createdBy معرف المستخدم المنشئ/المحدث.
     * @return void
     * @throws \Throwable
     */
    public function createInstallmentSaleFinancialTransactions(Invoice $invoice, array $data, int $companyId, ?int $userId, int $createdBy): void;

    /**
     * يعكس/يحذف المعاملات المالية المرتبطة بفاتورة البيع بالتقسيط.
     *
     * @param Invoice $invoice الفاتورة المراد عكس معاملاتها.
     * @return void
     * @throws \Throwable
     */
    public function reverseInstallmentSaleFinancialTransactions(Invoice $invoice): void;
}

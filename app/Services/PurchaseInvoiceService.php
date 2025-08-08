<?php

namespace App\Services\Invoice; // تم تعديل namespace ليكون App\Services\Invoice

use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use App\Services\Financial\FinancialServiceInterface;
use App\Models\User; // قد تحتاجها إذا كان المورد مستخدمًا
use Illuminate\Validation\ValidationException; // تم إزالة هذا الاستيراد إذا لم يعد يستخدم

class PurchaseInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    protected FinancialServiceInterface $financialService;

    // حقن FinancialService عبر Constructor
    public function __construct(FinancialServiceInterface $financialService)
    {
        $this->financialService = $financialService;
    }

    /**
     * إنشاء فاتورة شراء جديدة.
     *
     * @param array $data بيانات فاتورة الشراء.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            Log::info('PurchaseInvoiceService: بدء إنشاء فاتورة شراء.', ['data' => $data]);

            // 1. لا يوجد حساب أرباح تقديرية لفواتير الشراء.
            //    لكن قد تحتاج لتعيين بعض الحقول الافتراضية إذا لم يتم إرسالها.
            $data['status'] = $data['status'] ?? 'confirmed'; // حالة افتراضية
            $data['estimated_profit'] = 0; // لا يوجد ربح تقديري لفاتورة الشراء
            $data['remaining_amount'] = $data['net_amount'] - ($data['paid_amount'] ?? 0);

            // 2. إنشاء الفاتورة نفسها
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء فاتورة الشراء.');
            }

            // 3. إنشاء بنود الفاتورة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'], $data['created_by']);

            // 4. زيادة المخزون للبنود المشتراة
            $this->incrementStockForItems($data['items'], $data['company_id'], $data['created_by']);

            // 5. إنشاء المعاملات المالية لفاتورة الشراء (قيود الاستحقاق)
            // هذه الطريقة سيتم تعريفها في FinancialServiceInterface و FinancialService
            $this->financialService->createPurchaseFinancialTransactions(
                $invoice,
                $data,
                $data['company_id'],
                $data['user_id'], // المورد هو الـ user_id هنا
                $data['created_by']
            );

            // 6. معالجة الدفعة الأولية إذا كانت موجودة (دفعة صادرة)
            $paidAmount = $data['paid_amount'] ?? 0;
            $cashBoxId = $data['cash_box_id'] ?? null;
            if ($paidAmount > 0 && $cashBoxId) {
                // هذه الطريقة سيتم تعريفها في FinancialServiceInterface و FinancialService
                $this->financialService->handlePurchasePayment(
                    $invoice,
                    $paidAmount,
                    $cashBoxId,
                    $data['company_id'],
                    $data['user_id'], // المورد هو الـ user_id هنا
                    $data['created_by']
                );
            }

            $invoice->logCreated('إنشاء فاتورة شراء رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في إنشاء فاتورة الشراء.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * تحديث فاتورة شراء موجودة.
     *
     * @param array $data البيانات الجديدة لفاتورة الشراء.
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('PurchaseInvoiceService: بدء تحديث فاتورة شراء.', ['invoice_id' => $invoice->id, 'data' => $data]);

            // جلب الفاتورة بحالتها الأصلية قبل التحديث للحصول على القيم القديمة
            $freshInvoice = Invoice::with(['items', 'payments', 'financialTransactions'])->find($invoice->id);
            if (!$freshInvoice) {
                throw new \Exception("فشل العثور على الفاتورة (ID: {$invoice->id}) أثناء التحديث.");
            }

            $oldPaidAmount = $freshInvoice->paid_amount;

            // 1. خصم المخزون القديم (عكس الزيادة السابقة)
            $this->decrementStockForInvoiceItems($freshInvoice);

            // 2. تحديث بيانات الفاتورة (لا يوجد حساب أرباح هنا)
            $data['estimated_profit'] = 0; // التأكد من أن الربح التقديري يبقى صفرًا
            $data['remaining_amount'] = $data['net_amount'] - ($data['paid_amount'] ?? 0);
            $this->updateInvoice($invoice, $data);

            // 3. مزامنة بنود الفاتورة وزيادة المخزون للبنود الجديدة/المحدثة
            $this->syncInvoiceItems($invoice, $data['items'] ?? [], $data['company_id'], $data['updated_by']);
            $this->incrementStockForItems($data['items'] ?? [], $data['company_id'], $data['updated_by']);

            // 4. تحديث المعاملات المالية المرتبطة بفاتورة الشراء
            // هذه الطريقة سيتم تعريفها في FinancialServiceInterface و FinancialService
            $this->financialService->updatePurchaseFinancialTransactions(
                $invoice,
                $data,
                $data['company_id'],
                $data['user_id'] ?? $freshInvoice->user_id,
                $data['updated_by']
            );

            // 5. معالجة فرق المبلغ المدفوع (إنشاء دفعة جديدة أو تعديل الدفعات الموجودة)
            $newPaidAmount = $data['paid_amount'] ?? $oldPaidAmount;
            $paidAmountDifference = $newPaidAmount - $oldPaidAmount;

            if ($paidAmountDifference > 0) {
                // تم دفع مبلغ إضافي، إنشاء دفعة صادرة جديدة
                $cashBoxId = $data['cash_box_id'] ?? null;
                if ($cashBoxId) {
                    // هذه الطريقة سيتم تعريفها في FinancialServiceInterface و FinancialService
                    $this->financialService->handlePurchasePayment(
                        $invoice,
                        $paidAmountDifference,
                        $cashBoxId,
                        $data['company_id'],
                        $data['user_id'] ?? $freshInvoice->user_id,
                        $data['updated_by']
                    );
                } else {
                    Log::warning('PurchaseInvoiceService: لا يمكن معالجة دفعة إضافية بدون cash_box_id.', ['invoice_id' => $invoice->id, 'paid_amount_difference' => $paidAmountDifference]);
                }
            } elseif ($paidAmountDifference < 0) {
                // تم تقليل المبلغ المدفوع (مبلغ مسترد من المورد أو تعديل)
                // هذا يتطلب عملية "إرجاع شراء" أو "إشعار دائن" منفصلة يتم التعامل معها بواسطة FinancialService
                Log::warning('PurchaseInvoiceService: محاولة تقليل المبلغ المدفوع في تحديث فاتورة الشراء. هذا يتطلب عملية إرجاع منفصلة.', [
                    'invoice_id' => $invoice->id,
                    'old_paid_amount' => $oldPaidAmount,
                    'new_paid_amount' => $newPaidAmount
                ]);
                // يمكنك هنا رمي استثناء إذا كنت لا تسمح بهذا مباشرة
                // throw new \Exception('لا يمكن تقليل المبلغ المدفوع مباشرة من تحديث فاتورة الشراء. يرجى استخدام عملية الإرجاع.');
            }

            $invoice->logUpdated('تحديث فاتورة شراء رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في تحديث فاتورة الشراء.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إلغاء فاتورة شراء.
     *
     * @param Invoice $invoice الفاتورة المراد إلغاؤها.
     * @return Invoice الفاتورة الملغاة.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            Log::info('PurchaseInvoiceService: بدء إلغاء فاتورة شراء.', ['invoice_id' => $invoice->id]);

            // 1. خصم المخزون (عكس الزيادة السابقة)
            $this->decrementStockForInvoiceItems($invoice);

            // 2. عكس/حذف الدفعات والمعاملات المالية المرتبطة بها باستخدام الخدمة المالية
            // هذه الطريقة سيتم تعريفها في FinancialServiceInterface و FinancialService
            $this->financialService->reversePurchasePayments($invoice);

            // 3. عكس/حذف المعاملات المالية المرتبطة بفاتورة الشراء نفسها
            // هذه الطريقة سيتم تعريفها في FinancialServiceInterface و FinancialService
            $this->financialService->reversePurchaseFinancialTransactions($invoice);

            // 4. تحديث حالة الفاتورة إلى 'canceled' والقيام بـ soft delete لبنودها
            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice); // يقوم بـ soft delete لبنود الفاتورة

            $invoice->logCanceled('إلغاء فاتورة شراء رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في إلغاء فاتورة الشراء.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

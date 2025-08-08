<?php

namespace App\Services\Invoice; // تم تعديل namespace ليكون App\Services\Invoice

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use App\Services\Invoice\InstallmentService;
use App\Services\Financial\FinancialServiceInterface; // استيراد الواجهة الجديدة


class SaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    protected FinancialServiceInterface $financialService;

    // حقن FinancialService عبر Constructor
    public function __construct(FinancialServiceInterface $financialService)
    {
        $this->financialService = $financialService;
    }

    /**
     * إنشاء فاتورة بيع جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            Log::info('SaleInvoiceService: بدء إنشاء فاتورة بيع.', ['data' => $data]);

            // 1. التحقق من توفر المخزون قبل أي عملية مالية أو إنشاء فاتورة
            $this->checkVariantsStock($data['items']);

            // 2. حساب البيانات المالية للفاتورة (الربح التقديري، الحالة، المتبقي) باستخدام الخدمة المالية
            $data = $this->financialService->calculateInvoiceFinancials($data);

            // 3. إنشاء الفاتورة نفسها
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            // 4. إنشاء بنود الفاتورة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'], $data['created_by']);

            // 5. خصم المخزون
            $this->deductStockForItems($data['items']);

            // 6. إنشاء المعاملات المالية للفاتورة (قيود الاستحقاق)
            $this->financialService->createInvoiceFinancialTransactions(
                $invoice,
                $data,
                $data['company_id'],
                $data['user_id'],
                $data['created_by']
            );

            // 7. معالجة الدفعة الأولية إذا كانت موجودة
            $paidAmount = $data['paid_amount'] ?? 0;
            $cashBoxId = $data['cash_box_id'] ?? null;
            if ($paidAmount > 0 && $cashBoxId) {
                $this->financialService->handleInvoicePayment(
                    $invoice,
                    $paidAmount,
                    $cashBoxId,
                    $data['company_id'],
                    $data['user_id'],
                    $data['created_by']
                );
            }

            // 8. إنشاء خطة الأقساط إذا كانت موجودة
            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            $invoice->logCreated('إنشاء فاتورة بيع رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في إنشاء فاتورة البيع.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * تحديث فاتورة بيع موجودة.
     *
     * @param array $data البيانات الجديدة للفاتورة.
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('SaleInvoiceService: بدء تحديث فاتورة بيع.', ['invoice_id' => $invoice->id, 'data' => $data]);

            // جلب الفاتورة بحالتها الأصلية قبل التحديث للحصول على القيم القديمة
            // يجب تحميل العلاقات الضرورية هنا إذا كانت FinancialService تعتمد عليها
            $freshInvoice = Invoice::with(['items', 'payments', 'financialTransactions'])->find($invoice->id);
            if (!$freshInvoice) {
                throw new \Exception("فشل العثور على الفاتورة (ID: {$invoice->id}) أثناء التحديث.");
            }

            $oldPaidAmount = $freshInvoice->paid_amount;
            $oldEstimatedProfit = $freshInvoice->estimated_profit;

            // 1. إعادة المخزون للبنود القديمة قبل تحديثها أو حذفها
            $this->returnStockForItems($freshInvoice);

            // 2. إلغاء خطة الأقساط القديمة إذا كانت موجودة (سيتم إعادة إنشائها لاحقاً إذا لزم الأمر)
            if ($freshInvoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($freshInvoice);
            }

            // 3. حساب البيانات المالية للفاتورة (الربح التقديري، الحالة، المتبقي) باستخدام الخدمة المالية
            $data = $this->financialService->calculateInvoiceFinancials($data);

            // 4. تحديث الفاتورة نفسها
            $this->updateInvoice($invoice, $data);

            // 5. مزامنة بنود الفاتورة وتخصيم المخزون للبنود الجديدة/المحدثة
            $this->checkVariantsStock($data['items'] ?? []); // التحقق من المخزون للبنود الجديدة
            $this->syncInvoiceItems($invoice, $data['items'] ?? [], $data['company_id'], $data['updated_by']);
            $this->deductStockForItems($data['items'] ?? []);

            // 6. تحديث المعاملات المالية المرتبطة بالفاتورة
            $this->financialService->updateInvoiceFinancialTransactions(
                $invoice,
                $data,
                $data['company_id'],
                $data['user_id'] ?? $freshInvoice->user_id,
                $data['updated_by']
            );

            // 7. معالجة فرق المبلغ المدفوع (إنشاء دفعة جديدة أو تعديل الدفعات الموجودة)
            $newPaidAmount = $data['paid_amount'] ?? $oldPaidAmount;
            $paidAmountDifference = $newPaidAmount - $oldPaidAmount;

            if ($paidAmountDifference > 0) {
                // تم دفع مبلغ إضافي، إنشاء دفعة جديدة
                $cashBoxId = $data['cash_box_id'] ?? null;
                if ($cashBoxId) {
                    $this->financialService->handleInvoicePayment(
                        $invoice,
                        $paidAmountDifference,
                        $cashBoxId,
                        $data['company_id'],
                        $data['user_id'] ?? $freshInvoice->user_id,
                        $data['updated_by']
                    );
                } else {
                    Log::warning('SaleInvoiceService: لا يمكن معالجة دفعة إضافية بدون cash_box_id.', ['invoice_id' => $invoice->id, 'paid_amount_difference' => $paidAmountDifference]);
                }
            } elseif ($paidAmountDifference < 0) {
                // تم تقليل المبلغ المدفوع (مبلغ مسترد أو تعديل)
                // هذا يتطلب عملية "إرجاع" أو "إشعار دائن" منفصلة يتم التعامل معها بواسطة FinancialService
                Log::warning('SaleInvoiceService: محاولة تقليل المبلغ المدفوع في تحديث الفاتورة. هذا يتطلب عملية إرجاع منفصلة.', [
                    'invoice_id' => $invoice->id,
                    'old_paid_amount' => $oldPaidAmount,
                    'new_paid_amount' => $newPaidAmount
                ]);
                // يمكنك هنا رمي استثناء إذا كنت لا تسمح بهذا مباشرة
                // throw new \Exception('لا يمكن تقليل المبلغ المدفوع مباشرة من تحديث الفاتورة. يرجى استخدام عملية الإرجاع.');
            }

            // 8. تحديث الربح الفعلي المحقق في الدفعات الموجودة إذا تغير الربح التقديري للفاتورة
            if ($invoice->estimated_profit != $oldEstimatedProfit && $oldEstimatedProfit != 0) {
                // هذه العملية يمكن أن تكون جزءًا من FinancialService
                // ولكن بما أننا نحتاج إلى تكرار على دفعات الفاتورة، يمكن تركها هنا أو نقلها إلى FinancialService
                foreach ($invoice->payments as $payment) {
                    $newRealizedProfit = ($invoice->net_amount > 0) ? ($payment->amount / $invoice->net_amount) * $invoice->estimated_profit : 0;
                    $payment->update(['realized_profit_amount' => $newRealizedProfit]);
                }
            }


            // 9. إعادة إنشاء خطة الأقساط إذا كانت موجودة في البيانات الجديدة
            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            $invoice->logUpdated('تحديث فاتورة بيع رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في تحديث فاتورة البيع.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إلغاء فاتورة بيع.
     *
     * @param Invoice $invoice الفاتورة المراد إلغاؤها.
     * @return Invoice الفاتورة الملغاة.
     * @throws \Exception إذا كانت الفاتورة مدفوعة بالكامل.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            Log::info('SaleInvoiceService: بدء إلغاء فاتورة بيع.', ['invoice_id' => $invoice->id]);

            // 1. عكس تأثير الأقساط المرتبطة (إذا كانت موجودة)
            if ($invoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($invoice);
            }

            // 2. عكس تأثير المخزون (إعادة المنتجات إلى المخزون)
            $this->returnStockForItems($invoice);

            // 3. عكس/حذف الدفعات والمعاملات المالية المرتبطة بها باستخدام الخدمة المالية
            $this->financialService->reverseInvoicePayments($invoice);

            // 4. عكس/حذف المعاملات المالية المرتبطة بالفاتورة نفسها (الإيرادات، تكلفة البضاعة المباعة)
            $this->financialService->reverseInvoiceFinancialTransactions($invoice);

            // 5. تحديث حالة الفاتورة إلى 'canceled' والقيام بـ soft delete لبنودها
            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice); // يقوم بـ soft delete لبنود الفاتورة

            $invoice->logCanceled('إلغاء فاتورة بيع رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في إلغاء فاتورة البيع.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

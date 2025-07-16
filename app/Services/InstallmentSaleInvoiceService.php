<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use App\Services\InstallmentService;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Invoice\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InstallmentSaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    /**
     * إنشاء فاتورة بيع بالتقسيط جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            Log::info('InstallmentSaleInvoiceService: بدء إنشاء فاتورة بيع بالتقسيط.', ['data' => $data]);

            $this->checkVariantsStock($data['items']);
            Log::info('InstallmentSaleInvoiceService: تم التحقق من المخزون.');

            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }
            Log::info('InstallmentSaleInvoiceService: تم إنشاء الفاتورة الرئيسية.', ['invoice_id' => $invoice->id]);

            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
            Log::info('InstallmentSaleInvoiceService: تم إنشاء بنود الفاتورة.');

            $this->deductStockForItems($data['items']);
            Log::info('InstallmentSaleInvoiceService: تم خصم المخزون.');

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;
            $buyer = User::find($data['user_id']);

            $downPayment = $data['installment_plan']['down_payment'] ?? 0;
            if ($downPayment > 0) {
                Log::info('InstallmentSaleInvoiceService: معالجة الدفعة الأولى من خطة الأقساط.', [
                    'down_payment' => $downPayment,
                    'cash_box_id' => $cashBoxId
                ]);

                if ($authUser) {
                    $depositResult = $authUser->deposit($downPayment, $cashBoxId);
                    if ($depositResult !== true) {
                        throw new \Exception('فشل إيداع الدفعة الأولى في خزنة الموظف: ' . json_encode($depositResult));
                    }
                    Log::info('InstallmentSaleInvoiceService: تم إيداع الدفعة الأولى في خزنة البائع.', ['result' => $depositResult]);
                } else {
                    Log::warning('InstallmentSaleInvoiceService: لم يتم العثور على الموظف البائع لمعالجة الدفعة الأولى.');
                }
            }

            $installmentDebt = 0;
            if (isset($data['installment_plan'])) {
                $totalInstallmentAmount = $data['installment_plan']['total_amount'] ?? 0;
                $installmentDebt = $totalInstallmentAmount - $downPayment;
            }

            Log::info('InstallmentSaleInvoiceService: معالجة رصيد العميل بناءً على دين التقسيط الفعلي.', [
                'buyer_id' => $buyer->id ?? 'N/A',
                'installment_debt' => $installmentDebt
            ]);

            if ($buyer && $installmentDebt !== 0) {
                if ($installmentDebt > 0) {
                    $incurDebtResult = $buyer->withdraw($installmentDebt, $userCashBoxId);
                    if ($incurDebtResult !== true) {
                        throw new \Exception('فشل تسجيل دين التقسيط على العميل: ' . json_encode($incurDebtResult));
                    }
                    Log::info('InstallmentSaleInvoiceService: تم تسجيل دين التقسيط على العميل.', ['result' => $incurDebtResult]);
                } else {
                    $depositResult = $buyer->deposit(abs($installmentDebt), $userCashBoxId);
                    if ($depositResult !== true) {
                        throw new \Exception('فشل إيداع المبلغ الزائد في رصيد العميل: ' . json_encode($depositResult));
                    }
                    Log::info('InstallmentSaleInvoiceService: تم إيداع مبلغ زائد في رصيد العميل.', ['result' => $depositResult]);
                }
            } else {
                Log::info('InstallmentSaleInvoiceService: لا يوجد دين تقسيط لمعالجته أو لم يتم العثور على العميل.', [
                    'buyer_id' => $buyer->id ?? 'N/A',
                    'installment_debt' => $installmentDebt
                ]);
            }

            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
                Log::info('InstallmentSaleInvoiceService: تم إنشاء خطة أقساط.', ['invoice_id' => $invoice->id]);
            }

            $invoice->logCreated('إنشاء فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);
            Log::info('InstallmentSaleInvoiceService: تم إنشاء فاتورة بيع بالتقسيط بنجاح.', ['invoice_id' => $invoice->id]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في إنشاء فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * تحديث فاتورة بيع بالتقسيط موجودة.
     *
     * @param array $data البيانات الجديدة للفاتورة.
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('InstallmentSaleInvoiceService: بدء تحديث فاتورة بيع بالتقسيط.', ['invoice_id' => $invoice->id, 'data' => $data]);

            $this->cancel($invoice);
            Log::info('InstallmentSaleInvoiceService: تم إلغاء الفاتورة القديمة بنجاح كجزء من عملية التحديث.', ['invoice_id' => $invoice->id]);

            $newInvoice = $this->create($data);
            Log::info('InstallmentSaleInvoiceService: تم إنشاء فاتورة جديدة بنجاح كجزء من عملية التحديث.', ['new_invoice_id' => $newInvoice->id]);

            $newInvoice->logUpdated('تحديث فاتورة بيع بالتقسيط رقم ' . $newInvoice->invoice_number . ' (تم استبدال الفاتورة القديمة ' . $invoice->invoice_number . ')');
            Log::info('InstallmentSaleInvoiceService: تم تحديث فاتورة بيع بالتقسيط بنجاح (عبر الإلغاء والإنشاء).', ['invoice_id' => $newInvoice->id]);

            return $newInvoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في تحديث فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إلغاء فاتورة بيع بالتقسيط.
     *
     * @param Invoice $invoice الفاتورة المراد إلغاؤها.
     * @return Invoice الفاتورة الملغاة.
     * @throws \Exception إذا كانت الفاتورة مدفوعة بالكامل أو بها أقساط مدفوعة.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            Log::info('InstallmentSaleInvoiceService: بدء إلغاء فاتورة بيع بالتقسيط.', ['invoice_id' => $invoice->id]);

            if ($invoice->status === 'canceled') {
                throw new \Exception('لا يمكن إلغاء فاتورة ملغاة بالفعل.');
            }

            $this->returnStockForItems($invoice);
            Log::info('InstallmentSaleInvoiceService: تم استرجاع المخزون للعناصر الملغاة.');

            $totalPaidInstallmentsAmount = 0;
            if ($invoice->installmentPlan) {
                $totalPaidInstallmentsAmount = app(InstallmentService::class)->cancelInstallments(
                    $invoice,
                    $invoice->cash_box_id,
                    $invoice->user_cash_box_id
                );
                Log::info('InstallmentSaleInvoiceService: تم إلغاء خطة الأقساط والأقساط المرتبطة بالفاتورة.', ['invoice_id' => $invoice->id, 'total_paid_installments_amount' => $totalPaidInstallmentsAmount]);
            } else {
                Log::warning('InstallmentSaleInvoiceService: لا توجد خطة أقساط مرتبطة بالفاتورة للإلغاء.', ['invoice_id' => $invoice->id]);
            }

            $authUser = Auth::user();
            $buyer = User::find($invoice->user_id);

            $cashBoxId = $invoice->cash_box_id;
            $userCashBoxId = $invoice->user_cash_box_id;

            // عكس الدفعة الأولى التي استلمها الموظف
            $initialDownPayment = $invoice->installmentPlan->down_payment ?? 0;
            if ($initialDownPayment > 0) {
                Log::info('InstallmentSaleInvoiceService: سحب الدفعة الأولى المستردة من خزنة الموظف (إلغاء).', [
                    'seller_id' => $authUser->id,
                    'amount' => $initialDownPayment,
                    'cash_box_id' => $cashBoxId
                ]);
                $withdrawResult = $authUser->withdraw($initialDownPayment, $cashBoxId);
                if ($withdrawResult !== true) {
                    Log::error('InstallmentSaleInvoiceService: فشل سحب الدفعة الأولى المستردة من خزنة الموظف.', ['result' => $withdrawResult]);
                }
                Log::info('InstallmentSaleInvoiceService: تم سحب الدفعة الأولى المستردة من خزنة الموظف.', ['result' => $withdrawResult]);
            }

            // عكس دين التقسيط الذي تحمله العميلcancelInstallments
            $totalInstallmentDebt = ($invoice->installmentPlan->total_amount ?? 0) - ($invoice->installmentPlan->down_payment ?? 0);

            // تعديل الدين الصافي الذي يحتاج العميل لمسحه، مع الأخذ في الاعتبار الأقساط المدفوعة التي تم عكسها بالفعل
            $netDebtToClearFromCustomer = $totalInstallmentDebt - $totalPaidInstallmentsAmount;

            if ($buyer && $netDebtToClearFromCustomer !== 0) {
                if ($netDebtToClearFromCustomer > 0) {
                    // العميل لا يزال مديناً بهذا المبلغ، يتم إيداعه في رصيده لمسح الدين
                    Log::info('InstallmentSaleInvoiceService: إيداع مبلغ الدين الصافي المتبقي الملغى في رصيد العميل (إلغاء).', [
                        'buyer_id' => $buyer->id,
                        'amount' => $netDebtToClearFromCustomer,
                        'user_cash_box_id' => $userCashBoxId
                    ]);
                    $depositResult = $buyer->deposit($netDebtToClearFromCustomer, $userCashBoxId);
                    if ($depositResult !== true) {
                        Log::error('InstallmentSaleInvoiceService: فشل إيداع مبلغ الدين الصافي المتبقي الملغى في رصيد العميل.', ['result' => $depositResult]);
                    }
                    Log::info('InstallmentSaleInvoiceService: تم إيداع مبلغ الدين الصافي المتبقي الملغى في رصيد العميل.', ['result' => $depositResult]);
                } elseif ($netDebtToClearFromCustomer < 0) {
                    // هذا يعني أن العميل دفع أكثر من إجمالي الدين، يجب سحب المبلغ الزائد
                    Log::info('InstallmentSaleInvoiceService: سحب مبلغ زائد مدفوع من رصيد العميل (إلغاء).', [
                        'buyer_id' => $buyer->id,
                        'amount' => abs($netDebtToClearFromCustomer),
                        'user_cash_box_id' => $userCashBoxId
                    ]);
                    $withdrawResult = $buyer->withdraw(abs($netDebtToClearFromCustomer), $userCashBoxId);
                    if ($withdrawResult !== true) {
                        Log::error('InstallmentSaleInvoiceService: فشل سحب المبلغ الزائد من رصيد العميل.', ['result' => $withdrawResult]);
                    }
                    Log::info('InstallmentSaleInvoiceService: تم سحب مبلغ زائد مدفوع من رصيد العميل.', ['result' => $withdrawResult]);
                }
            } else {
                Log::info('InstallmentSaleInvoiceService: لا يوجد دين صافي متبقي لمعالجته أو لم يتم العثور على العميل عند الإلغاء.', [
                    'buyer_id' => $buyer->id ?? 'N/A',
                    'net_debt_to_clear_from_customer' => $netDebtToClearFromCustomer
                ]);
            }

            $invoice->update([
                'status' => 'canceled',
            ]);
            Log::info('InstallmentSaleInvoiceService: تم تغيير حالة الفاتورة إلى ملغاة.', ['invoice_id' => $invoice->id]);

            $this->deleteInvoiceItems($invoice);
            Log::info('InstallmentSaleInvoiceService: تم حذف بنود الفاتورة.', ['invoice_id' => $invoice->id]);

            $invoice->logCanceled('إلغاء فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);
            Log::info('InstallmentSaleInvoiceService: تم إلغاء فاتورة بيع بالتقسيط بنجاح.', ['invoice_id' => $invoice->id]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في إلغاء فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

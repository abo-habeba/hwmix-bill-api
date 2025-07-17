<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use App\Services\InstallmentService;
use App\Services\UserSelfDebtService; // سنستخدمها هنا
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class InstallmentSaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    protected UserSelfDebtService $userSelfDebtService;

    public function __construct(UserSelfDebtService $userSelfDebtService)
    {
        $this->userSelfDebtService = $userSelfDebtService;
    }

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
            // التحقق من توافر المنتجات في المخزون
            $this->checkVariantsStock($data['items']);

            // إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            // إنشاء بنود الفاتورة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            // خصم الكمية من المخزون
            $this->deductStockForItems($data['items']);

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;
            $buyer = User::find($data['user_id']);

            $downPayment = $data['installment_plan']['down_payment'] ?? 0;

            // معالجة الدفعة الأولى (تودع في خزنة البائع)
            if ($downPayment > 0) {
                if (!$authUser) {
                    throw new \Exception('لا يمكن معالجة الدفعة الأولى. لم يتم تحديد الموظف البائع.');
                }
                $depositResult = $authUser->deposit($downPayment, $cashBoxId);
                if ($depositResult !== true) {
                    throw new \Exception('فشل إيداع الدفعة الأولى في خزنة الموظف: ' . json_encode($depositResult));
                }
            }

            // حساب دين التقسيط الفعلي المتبقي على العميل
            $totalInstallmentAmount = $data['installment_plan']['total_amount'] ?? 0;
            $installmentDebt = $totalInstallmentAmount - $downPayment;

            // معالجة رصيد العميل بناءً على دين التقسيط
            if ($buyer) {
                if ($buyer->id == $authUser->id) {
                    // العميل هو نفس الموظف (البيع للنفس)
                    $this->userSelfDebtService->handleSelfSaleDebt($authUser, $invoice, $downPayment, $totalInstallmentAmount, $cashBoxId, $userCashBoxId);
                } else {
                    // العميل هو مستخدم آخر
                    if ($installmentDebt > 0) {
                        // العميل مدين للشركة (رصيد العميل يصبح سالباً = دين عليه)
                        $withdrawResult = $buyer->withdraw($installmentDebt, $userCashBoxId);
                        if ($withdrawResult !== true) {
                            throw new \Exception('فشل تسجيل دين التقسيط على العميل: ' . json_encode($withdrawResult));
                        }
                    } elseif ($installmentDebt < 0) {
                        // العميل دفع أكثر من المستحق (رصيد العميل يصبح موجباً)
                        $depositResult = $buyer->deposit(abs($installmentDebt), $userCashBoxId);
                        if ($depositResult !== true) {
                            throw new \Exception('فشل إيداع المبلغ الزائد في رصيد العميل: ' . json_encode($depositResult));
                        }
                    }
                }
            } else {
                Log::warning('InstallmentSaleInvoiceService: لم يتم العثور على العميل لتسجيل دين التقسيط.', ['user_id' => $data['user_id']]);
            }

            // إنشاء خطة الأقساط
            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            // تسجيل عملية الإنشاء
            $invoice->logCreated('إنشاء فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);

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
            // إلغاء الفاتورة القديمة أولاً (يعكس جميع التأثيرات المالية والمخزنية)
            // ملاحظة: دالة cancel ستحدث حالة الفاتورة القديمة إلى 'canceled'
            $this->cancel($invoice);

            // إعادة إنشاء فاتورة جديدة بالبيانات المحدثة
            $newInvoice = $this->create($data);

            // تسجيل عملية التحديث للفاتورة الجديدة
            $newInvoice->logUpdated('تحديث فاتورة بيع بالتقسيط رقم ' . $newInvoice->invoice_number . ' (تم استبدال الفاتورة القديمة ' . $invoice->invoice_number . ')');

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
            if ($invoice->status === 'canceled') {
                throw new \Exception('لا يمكن إلغاء فاتورة ملغاة بالفعل.');
            }

            $authUser = Auth::user();
            $buyer = User::find($invoice->user_id);
            $cashBoxId = $invoice->cash_box_id;
            $userCashBoxId = $invoice->user_cash_box_id;

            // استرجاع المخزون (إلغاء خصم المخزون الأصلي)
            $this->returnStockForItems($invoice);

            // إلغاء خطة الأقساط والأقساط المدفوعة (ويقوم بإرجاع المبالغ المدفوعة إلى العميل)
            $totalPaidInstallmentsAmount = 0;
            if ($invoice->installmentPlan) {
                // InstallmentService::cancelInstallments يجب أن تقوم بعكس دفعات الأقساط
                // وإرجاع المبالغ إلى رصيد العميل المناسب
                $totalPaidInstallmentsAmount = app(InstallmentService::class)->cancelInstallments(
                    $invoice,
                    $cashBoxId,
                    $userCashBoxId
                );
            } else {
                Log::warning('InstallmentSaleInvoiceService: لا توجد خطة أقساط مرتبطة بالفاتورة للإلغاء.', ['invoice_id' => $invoice->id]);
            }

            // عكس الدفعة الأولى التي استلمها الموظف
            $initialDownPayment = $invoice->installmentPlan->down_payment ?? 0;
            if ($initialDownPayment > 0) {
                // المبلغ المستلم كدفعة أولى يجب أن يخصم من خزنة البائع (الموظف)
                if (!$authUser) {
                    Log::error('InstallmentSaleInvoiceService: لم يتم العثور على الموظف البائع لعكس الدفعة الأولى.');
                } else {
                    $withdrawResult = $authUser->withdraw($initialDownPayment, $cashBoxId);
                    if ($withdrawResult !== true) {
                        Log::error('InstallmentSaleInvoiceService: فشل سحب الدفعة الأولى المستردة من خزنة الموظف.', ['result' => $withdrawResult]);
                    }
                }
            }

            // عكس دين التقسيط الذي تحمله العميل
            $totalInstallmentDebt = ($invoice->installmentPlan->total_amount ?? 0) - ($invoice->installmentPlan->down_payment ?? 0);

            // المبلغ الإجمالي الذي يجب أن يودع في رصيد العميل لإنهاء دينه
            // (دين التقسيط الكلي - الأقساط التي تم عكسها بالفعل بواسطة InstallmentService)
            $netDebtToClearFromCustomer = $totalInstallmentDebt - $totalPaidInstallmentsAmount;

            if ($buyer) {
                if ($buyer->id == $authUser->id) {
                    // العميل هو نفس الموظف (البيع للنفس) - عكس الدين يتم عن طريق userSelfDebtService
                    $this->userSelfDebtService->clearSelfSaleDebt($authUser, $invoice, $cashBoxId, $userCashBoxId);
                } else {
                    // العميل هو مستخدم آخر
                    if ($netDebtToClearFromCustomer > 0) {
                        // العميل لا يزال مديناً بهذا المبلغ، يتم إيداعه في رصيده لمسح الدين
                        $depositResult = $buyer->deposit($netDebtToClearFromCustomer, $userCashBoxId);
                        if ($depositResult !== true) {
                            Log::error('InstallmentSaleInvoiceService: فشل إيداع مبلغ الدين الصافي المتبقي الملغى في رصيد العميل.', ['result' => $depositResult]);
                        }
                    } elseif ($netDebtToClearFromCustomer < 0) {
                        // هذا يعني أن العميل كان قد دفع أكثر من اللازم، يجب سحب المبلغ الزائد من رصيده
                        $withdrawResult = $buyer->withdraw(abs($netDebtToClearFromCustomer), $userCashBoxId);
                        if ($withdrawResult !== true) {
                            Log::error('InstallmentSaleInvoiceService: فشل سحب المبلغ الزائد من رصيد العميل.', ['result' => $withdrawResult]);
                        }
                    }
                }
            } else {
                Log::warning('InstallmentSaleInvoiceService: لم يتم العثور على العميل عند الإلغاء.', ['user_id' => $invoice->user_id]);
            }

            // تغيير حالة الفاتورة إلى ملغاة
            $invoice->update(['status' => 'canceled']);

            // حذف بنود الفاتورة (اختياري ولكن شائع بعد الإلغاء)
            $this->deleteInvoiceItems($invoice);

            // تسجيل عملية الإلغاء
            $invoice->logCanceled('إلغاء فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في إلغاء فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

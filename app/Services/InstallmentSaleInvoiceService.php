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

            // متغيرات لتجميع التغيرات النهائية في الأرصدة
            $netCustomerBalanceChange = 0; // التغيير الصافي في رصيد العميل (موجب = إيداع (سداد دين)، سالب = سحب (تسجيل دين))
            $netStaffBalanceChange = 0;    // التغيير الصافي في رصيد الموظف (موجب = إيداع، سالب = سحب)

            // 1. استرجاع المخزون (إلغاء خصم المخزون الأصلي)
            $this->returnStockForItems($invoice);

            // 2. إلغاء خطة الأقساط والأقساط المدفوعة (تجميع المبالغ المدفوعة)
            // هذه الدالة الآن ترجع فقط إجمالي المبالغ التي دفعها العميل كأقساط.
            $totalPaidInstallmentsAmount = 0;
            if ($invoice->installmentPlan) {
                $totalPaidInstallmentsAmount = app(InstallmentService::class)->cancelInstallments($invoice);

                // بالنسبة للعميل: هذه المبالغ كانت قد خصمت من رصيده (سدد بها دين).
                // الآن يجب أن "تعود كدين عليه"، أي يجب أن تخصم من رصيده مرة أخرى (تجعل رصيده أكثر سلبية).
                $netCustomerBalanceChange -= $totalPaidInstallmentsAmount;

                // بالنسبة للموظف: هذه المبالغ كانت قد أودعت في رصيده.
                // الآن يجب أن تخصم من رصيده (تسحب منه).
                $netStaffBalanceChange -= $totalPaidInstallmentsAmount;

                Log::info('InstallmentSaleInvoiceService: إجمالي الأقساط المدفوعة لعكسها.', ['amount' => $totalPaidInstallmentsAmount]);
            } else {
                Log::warning('InstallmentSaleInvoiceService: لا توجد خطة أقساط مرتبطة بالفاتورة للإلغاء.', ['invoice_id' => $invoice->id]);
            }

            // 3. عكس الدفعة الأولى التي استلمها الموظف
            $initialDownPayment = $invoice->installmentPlan->down_payment ?? 0;
            if ($initialDownPayment > 0) {
                // هذا المبلغ تم إيداعه في خزنة الموظف عند إنشاء الفاتورة.
                // الآن يجب أن يخصم من خزنة البائع (الموظف).
                $netStaffBalanceChange -= $initialDownPayment;
                Log::info('InstallmentSaleInvoiceService: الدفعة الأولى لعكسها من الموظف.', ['amount' => $initialDownPayment]);
            }

            // 4. عكس دين التقسيط الكلي الذي تحمله العميل (الدين الأصلي للفاتورة)
            $totalInstallmentDebt = ($invoice->installmentPlan->total_amount ?? 0) - ($invoice->installmentPlan->down_payment ?? 0);
            if ($totalInstallmentDebt > 0) {
                // هذا المبلغ يمثل الدين الذي تم تسجيله على العميل عند إنشاء الفاتورة (تم خصمه من رصيده).
                // الآن يتم إيداعه في رصيد العميل لمسح هذا الدين (إعادته إلى رصيده الطبيعي قبل هذه الفاتورة).
                $netCustomerBalanceChange += $totalInstallmentDebt;
                Log::info('InstallmentSaleInvoiceService: إجمالي دين التقسيط الأصلي لعكسه للعميل.', ['amount' => $totalInstallmentDebt]);
            }

            // تطبيق التغيير الصافي على رصيد العميل
            if ($buyer) {
                if ($buyer->id == $authUser->id) {
                    // العميل هو نفس الموظف (البيع للنفس)
                    // هنا يجب مراجعة منطق userSelfDebtService->clearSelfSaleDebt
                    // للتأكد من أنه يتعامل مع netCustomerBalanceChange و netStaffBalanceChange
                    // أو أنه يقوم بتسوية شاملة تأخذ في الاعتبار الدفعة المقدمة والأقساط والدين الأصلي
                    // إذا لم يكن كذلك، فقد تحتاج إلى تمرير netCustomerBalanceChange و netStaffBalanceChange
                    // إلى خدمة userSelfDebtService أو معالجتها هنا مباشرةً
                    $this->userSelfDebtService->clearSelfSaleDebt($authUser, $invoice, $cashBoxId, $userCashBoxId);
                    Log::info('InstallmentSaleInvoiceService: تم معالجة دين البيع للنفس.', ['user_id' => $authUser->id]);
                } else {
                    // العميل هو مستخدم آخر - تطبيق التغيير الصافي
                    if ($netCustomerBalanceChange > 0) {
                        $depositResult = $buyer->deposit($netCustomerBalanceChange, $userCashBoxId);
                        if ($depositResult !== true) {
                            Log::error('InstallmentSaleInvoiceService: فشل إيداع التغيير الصافي في رصيد العميل.', ['amount' => $netCustomerBalanceChange, 'result' => $depositResult]);
                            throw new \Exception('فشل إيداع التغيير الصافي في رصيد العميل.');
                        }
                        Log::info('InstallmentSaleInvoiceService: تم إيداع التغيير الصافي في رصيد العميل.', ['customer_id' => $buyer->id, 'amount' => $netCustomerBalanceChange]);
                    } elseif ($netCustomerBalanceChange < 0) {
                        $withdrawResult = $buyer->withdraw(abs($netCustomerBalanceChange), $userCashBoxId);
                        if ($withdrawResult !== true) {
                            Log::error('InstallmentSaleInvoiceService: فشل سحب التغيير الصافي من رصيد العميل.', ['amount' => $netCustomerBalanceChange, 'result' => $withdrawResult]);
                            throw new \Exception('فشل سحب التغيير الصافي من رصيد العميل.');
                        }
                        Log::info('InstallmentSaleInvoiceService: تم سحب التغيير الصافي من رصيد العميل.', ['customer_id' => $buyer->id, 'amount' => abs($netCustomerBalanceChange)]);
                    } else {
                        Log::info('InstallmentSaleInvoiceService: لا يوجد تغيير صافي في رصيد العميل.', ['customer_id' => $buyer->id]);
                    }
                }
            } else {
                Log::warning('InstallmentSaleInvoiceService: لم يتم العثور على العميل عند الإلغاء.', ['user_id' => $invoice->user_id]);
            }

            // تطبيق التغيير الصافي على رصيد الموظف (البائع)
            if ($authUser && $netStaffBalanceChange !== 0) {
                if ($netStaffBalanceChange < 0) {
                    $withdrawResult = $authUser->withdraw(abs($netStaffBalanceChange), $cashBoxId);
                    if ($withdrawResult !== true) {
                        Log::error('InstallmentSaleInvoiceService: فشل سحب التغيير الصافي من رصيد الموظف.', ['amount' => $netStaffBalanceChange, 'result' => $withdrawResult]);
                        throw new \Exception('فشل سحب التغيير الصافي من رصيد الموظف.');
                    }
                    Log::info('InstallmentSaleInvoiceService: تم سحب التغيير الصافي من رصيد الموظف.', ['staff_id' => $authUser->id, 'amount' => abs($netStaffBalanceChange)]);
                } elseif ($netStaffBalanceChange > 0) {
                    $depositResult = $authUser->deposit($netStaffBalanceChange, $cashBoxId);
                    if ($depositResult !== true) {
                        Log::error('InstallmentSaleInvoiceService: فشل إيداع التغيير الصافي في رصيد الموظف.', ['amount' => $netStaffBalanceChange, 'result' => $depositResult]);
                        throw new \Exception('فشل إيداع التغيير الصافي في رصيد الموظف.');
                    }
                    Log::info('InstallmentSaleInvoiceService: تم إيداع التغيير الصافي في رصيد الموظف.', ['staff_id' => $authUser->id, 'amount' => $netStaffBalanceChange]);
                }
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

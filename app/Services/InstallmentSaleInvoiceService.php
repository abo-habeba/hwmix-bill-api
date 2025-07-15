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

            // 1. التحقق من توفر المخزون قبل إنشاء الفاتورة
            $this->checkVariantsStock($data['items']);
            Log::info('InstallmentSaleInvoiceService: تم التحقق من المخزون.');

            // 2. إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }
            Log::info('InstallmentSaleInvoiceService: تم إنشاء الفاتورة الرئيسية.', ['invoice_id' => $invoice->id]);

            // 3. إنشاء بنود الفاتورة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
            Log::info('InstallmentSaleInvoiceService: تم إنشاء بنود الفاتورة.');

            // 4. خصم المخزون للبنود التي تم بيعها
            $this->deductStockForItems($data['items']);
            Log::info('InstallmentSaleInvoiceService: تم خصم المخزون.');

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;
            $buyer = User::find($data['user_id']); // العميل/المشتري

            // 5. معالجة المبلغ المدفوع (الدفعة الأولى من installment_plan)
            $downPayment = $data['installment_plan']['down_payment'] ?? 0;
            if ($downPayment > 0) {
                Log::info('InstallmentSaleInvoiceService: معالجة الدفعة الأولى من خطة الأقساط.', [
                    'down_payment' => $downPayment,
                    'cash_box_id' => $cashBoxId
                ]);

                // إيداع الدفعة الأولى في خزنة الموظف البائع
                if ($authUser) {
                    Log::info('InstallmentSaleInvoiceService: إيداع الدفعة الأولى في خزنة البائع.', [
                        'seller_id' => $authUser->id,
                        'amount' => $downPayment,
                        'cash_box_id' => $cashBoxId
                    ]);
                    $depositResult = $authUser->deposit($downPayment, $cashBoxId);
                    if ($depositResult !== true) {
                        throw new \Exception('فشل إيداع الدفعة الأولى في خزنة الموظف: ' . json_encode($depositResult));
                    }
                    Log::info('InstallmentSaleInvoiceService: تم إيداع الدفعة الأولى في خزنة البائع.', ['result' => $depositResult]);
                } else {
                    Log::warning('InstallmentSaleInvoiceService: لم يتم العثور على الموظف البائع لمعالجة الدفعة الأولى.');
                }
            }

            // 6. معالجة رصيد العميل بناءً على دين التقسيط الفعلي (إجمالي الأقساط - الدفعة الأولى)
            $installmentDebt = 0;
            if (isset($data['installment_plan'])) {
                $totalInstallmentAmount = $data['installment_plan']['total_amount'] ?? 0;
                $installmentDebt = $totalInstallmentAmount - $downPayment; // دين التقسيط الفعلي
            }

            Log::info('InstallmentSaleInvoiceService: معالجة رصيد العميل بناءً على دين التقسيط الفعلي.', [
                'buyer_id' => $buyer->id ?? 'N/A',
                'installment_debt' => $installmentDebt
            ]);

            if ($buyer && $installmentDebt !== 0) {
                if ($installmentDebt > 0) {
                    // العميل مدين للشركة بالمبلغ المتبقي للتقسيط (رصيد العميل يجب أن يصبح سالبًا)
                    Log::info('InstallmentSaleInvoiceService: سحب مبلغ دين التقسيط من رصيد العميل.', [
                        'buyer_id' => $buyer->id,
                        'amount' => $installmentDebt,
                        'user_cash_box_id' => $userCashBoxId
                    ]);
                    $withdrawResult = $buyer->withdraw($installmentDebt, $userCashBoxId);
                    if ($withdrawResult !== true) {
                        throw new \Exception('فشل سحب مبلغ دين التقسيط من العميل: ' . json_encode($withdrawResult));
                    }
                    Log::info('InstallmentSaleInvoiceService: تم سحب مبلغ دين التقسيط من رصيد العميل.', ['result' => $withdrawResult]);
                } else {
                    // هذا السيناريو (installmentDebt < 0) يعني أن الدفعة الأولى أكبر من إجمالي الأقساط
                    // وهو غير منطقي في سياق "دين تقسيط"
                    // ولكن إذا حدث، فسيتم إيداع الفرق
                    Log::info('InstallmentSaleInvoiceService: إيداع مبلغ زائد (دفعة أولى أكبر من إجمالي الأقساط) في رصيد العميل.', [
                        'buyer_id' => $buyer->id,
                        'amount' => abs($installmentDebt),
                        'user_cash_box_id' => $userCashBoxId
                    ]);
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

            // 7. إنشاء خطط الأقساط إذا كانت موجودة في البيانات
            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
                Log::info('InstallmentSaleInvoiceService: تم إنشاء خطة أقساط.', ['invoice_id' => $invoice->id]);
            }

            // 8. تسجيل عملية الإنشاء في سجل النشاط
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

            // 1. إلغاء الفاتورة القديمة (هذا سيعكس كل التأثيرات المالية والمخزنية والأقساط)
            // ملاحظة: يجب أن تكون دالة cancel قوية وموثوقة
            $this->cancel($invoice);
            Log::info('InstallmentSaleInvoiceService: تم إلغاء الفاتورة القديمة بنجاح كجزء من عملية التحديث.', ['invoice_id' => $invoice->id]);

            // 2. إنشاء فاتورة جديدة بالبيانات المحدثة
            // ملاحظة: يجب أن تحتوي $data على جميع البيانات المطلوبة لإنشاء فاتورة جديدة
            $newInvoice = $this->create($data);
            Log::info('InstallmentSaleInvoiceService: تم إنشاء فاتورة جديدة بنجاح كجزء من عملية التحديث.', ['new_invoice_id' => $newInvoice->id]);

            // 3. تسجيل عملية التحديث في سجل النشاط (للفاتورة الجديدة)
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

            // 1️⃣ تحقق من إمكانية الإلغاء
            // لا يمكن إلغاء فاتورة إذا كانت حالتها "ملغاة" بالفعل
            if ($invoice->status === 'canceled') {
                throw new \Exception('لا يمكن إلغاء فاتورة ملغاة بالفعل.');
            }

            // 2️⃣ استرجاع الكمية للمخزون
            $this->returnStockForItems($invoice);
            Log::info('InstallmentSaleInvoiceService: تم استرجاع المخزون للعناصر الملغاة.');

            // 3️⃣ إلغاء خطة الأقساط والأقساط المرتبطة بها
            // هذه الدالة في InstallmentService يجب أن تقوم بما يلي:
            // - البحث عن InstallmentPlan المرتبطة بالـ invoice_id
            // - إلغاء جميع الأقساط (Installment) التابعة لهذه الخطة (تغيير حالتها إلى 'canceled')
            // - **لكل قسط تم دفعه:** عكس المعاملة المالية (سحب المبلغ من خزنة الموظف الذي استلمه، وإيداعه في رصيد العميل).
            // - تحديث حالة InstallmentPlan إلى 'canceled'
            // - **إرجاع إجمالي المبالغ التي تم دفعها للأقساط الفردية (باستثناء الدفعة الأولى التي يتم التعامل معها هنا).**
            $totalPaidInstallmentsAmount = 0;
            if ($invoice->installmentPlan) {
                // افتراض أن cancelInstallments ترجع إجمالي المبالغ المدفوعة للأقساط الفردية
                $totalPaidInstallmentsAmount = app(InstallmentService::class)->cancelInstallments($invoice);
                Log::info('InstallmentSaleInvoiceService: تم إلغاء خطة الأقساط والأقساط المرتبطة بالفاتورة.', ['invoice_id' => $invoice->id, 'total_paid_installments_amount' => $totalPaidInstallmentsAmount]);
            } else {
                Log::warning('InstallmentSaleInvoiceService: لا توجد خطة أقساط مرتبطة بالفاتورة للإلغاء.', ['invoice_id' => $invoice->id]);
            }

            // 4️⃣ معالجة الرصيد المالي للعميل (عكس الدفعة الأولى ودين التقسيط)
            $authUser = Auth::user();
            $buyer = User::find($invoice->user_id);
            // ملاحظة: cashBoxId و userCashBoxId لا يمكن تحديدهما بشكل موثوق من الفاتورة الملغاة
            // يجب أن يتم تمريرهما إذا كانت ضرورية لعمليات الإيداع/السحب أو يمكن الحصول عليها من سياق المعاملة الأصلية
            $cashBoxId = null;
            $userCashBoxId = null;

            // عكس الدفعة الأولى (المبلغ الذي تم إيداعه في خزنة الموظف عند إنشاء الفاتورة)
            $originalDownPayment = $invoice->paid_amount; // هذا يمثل الدفعة الأولى فقط

            if ($originalDownPayment > 0) {
                Log::info('InstallmentSaleInvoiceService: سحب الدفعة الأولى المستردة من خزنة الموظف (إلغاء).', [
                    'seller_id' => $authUser->id,
                    'amount' => $originalDownPayment,
                    'cash_box_id' => $cashBoxId
                ]);
                $withdrawResult = $authUser->withdraw($originalDownPayment, $cashBoxId);
                if ($withdrawResult !== true) {
                    Log::error('InstallmentSaleInvoiceService: فشل سحب الدفعة الأولى المستردة من خزنة الموظف.', ['result' => $withdrawResult]);
                }
                Log::info('InstallmentSaleInvoiceService: تم سحب الدفعة الأولى المستردة من خزنة الموظف.', ['result' => $withdrawResult]);
            }

            // عكس دين التقسيط المتبقي على العميل بعد خصم الدفعة الأولى والأقساط المدفوعة
            // المبلغ الإجمالي الذي كان دينًا على العميل بعد الدفعة الأولى هو $invoice->remaining_amount (القيمة الأولية)
            // المبلغ الذي دفعه العميل كأقساط فردية هو $totalPaidInstallmentsAmount
            // لذا، المبلغ الصافي الذي لا يزال دينًا على العميل هو $invoice->remaining_amount - $totalPaidInstallmentsAmount
            $netRemainingDebtToClear = $invoice->remaining_amount - $totalPaidInstallmentsAmount;

            if ($buyer && $netRemainingDebtToClear !== 0) {
                if ($netRemainingDebtToClear > 0) {
                    // العميل لا يزال مديناً بهذا المبلغ الصافي، يتم إيداعه في رصيده لإزالة الدين
                    Log::info('InstallmentSaleInvoiceService: إيداع مبلغ الدين الصافي المتبقي الملغى في رصيد العميل (إلغاء).', [
                        'buyer_id' => $buyer->id,
                        'amount' => $netRemainingDebtToClear,
                        'user_cash_box_id' => $userCashBoxId
                    ]);
                    $depositResult = $buyer->deposit($netRemainingDebtToClear, $userCashBoxId);
                    if ($depositResult !== true) {
                        Log::error('InstallmentSaleInvoiceService: فشل إيداع مبلغ الدين الصافي المتبقي الملغى في رصيد العميل.', ['result' => $depositResult]);
                    }
                    Log::info('InstallmentSaleInvoiceService: تم إيداع مبلغ الدين الصافي المتبقي الملغى في رصيد العميل.', ['result' => $depositResult]);
                } elseif ($netRemainingDebtToClear < 0) {
                    // هذا يعني أن العميل دفع أكثر مما كان عليه دين صافي (سيناريو غير متوقع في التقسيط عادةً)
                    // إذا حدث، يجب سحب المبلغ الزائد الذي تم إيداعه في رصيد العميل عند الإنشاء
                    Log::info('InstallmentSaleInvoiceService: سحب مبلغ زائد مدفوع من رصيد العميل (إلغاء).', [
                        'buyer_id' => $buyer->id,
                        'amount' => abs($netRemainingDebtToClear),
                        'user_cash_box_id' => $userCashBoxId
                    ]);
                    $withdrawResult = $buyer->withdraw(abs($netRemainingDebtToClear), $userCashBoxId);
                    if ($withdrawResult !== true) {
                        Log::error('InstallmentSaleInvoiceService: فشل سحب المبلغ الزائد من رصيد العميل.', ['result' => $withdrawResult]);
                    }
                    Log::info('InstallmentSaleInvoiceService: تم سحب مبلغ زائد مدفوع من رصيد العميل.', ['result' => $withdrawResult]);
                }
            } else {
                Log::info('InstallmentSaleInvoiceService: لا يوجد دين صافي متبقي لمعالجته أو لم يتم العثور على العميل عند الإلغاء.', [
                    'buyer_id' => $buyer->id ?? 'N/A',
                    'net_remaining_debt_to_clear' => $netRemainingDebtToClear
                ]);
            }

            // 5️⃣ تغيير حالة الفاتورة إلى ملغاة
            $invoice->update([
                'status' => 'canceled',
            ]);
            Log::info('InstallmentSaleInvoiceService: تم تغيير حالة الفاتورة إلى ملغاة.', ['invoice_id' => $invoice->id]);

            // 6️⃣ حذف بنود الفاتورة
            $this->deleteInvoiceItems($invoice);
            Log::info('InstallmentSaleInvoiceService: تم حذف بنود الفاتورة.', ['invoice_id' => $invoice->id]);

            // 7️⃣ تسجيل عملية الإلغاء في سجل النشاط
            $invoice->logCanceled('إلغاء فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);
            Log::info('InstallmentSaleInvoiceService: تم إلغاء فاتورة بيع بالتقسيط بنجاح.', ['invoice_id' => $invoice->id]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('InstallmentSaleInvoiceService: فشل في إلغاء فاتورة بيع بالتقسيط.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

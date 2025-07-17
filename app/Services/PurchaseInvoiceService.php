<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use Illuminate\Validation\ValidationException;
use App\Services\UserSelfDebtService; // سيتم إزالة استخدامها
use App\Services\InstallmentService; // قد لا تكون ضرورية لفاتورة الشراء

class PurchaseInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    /**
     * إنشاء فاتورة شراء جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            Log::info('PurchaseInvoiceService: بدء إنشاء فاتورة شراء.', ['data' => $data]);

            // التحقق من المنتجات والمتغيرات
            foreach ($data['items'] as $item) {
                $variant = ProductVariant::find($item['variant_id']);
                if (!$variant) {
                    throw ValidationException::withMessages([
                        'variant_id' => ['المتغير بمعرف ' . $item['variant_id'] . ' غير موجود.'],
                    ]);
                }
            }

            // إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            // إنشاء بنود الفاتورة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            // زيادة الكمية في المخزون للبنود المشتراة
            $this->incrementStockForItems($data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null; // user_cash_box_id هنا تخص المورد

            // معالجة رصيد الموظف (الخزنة) والمورد
            // المبلغ المدفوع يخصم من خزنة الموظف (الشركة تدفع)
            if ($invoice->paid_amount > 0) {
                $withdrawResult = $authUser->withdraw($invoice->paid_amount, $cashBoxId);
                if ($withdrawResult !== true) {
                    throw new \Exception('فشل سحب المبلغ المدفوع من خزنة الموظف: ' . json_encode($withdrawResult));
                }
            }

            // معالجة رصيد المورد بناءً على المبلغ المتبقي
            // (المبلغ المتبقي يضاف كدين على الشركة للمورد)
            if ($invoice->user_id) { // user_id في فاتورة الشراء هو معرف المورد
                $supplier = User::find($invoice->user_id);
                if ($supplier) {
                    if ($invoice->remaining_amount > 0) {
                        // الشركة مدينة للمورد (رصيد المورد يزيد)
                        $depositResult = $supplier->deposit($invoice->remaining_amount, $userCashBoxId);
                        if ($depositResult !== true) {
                            throw new \Exception('فشل إيداع مبلغ متبقي في رصيد المورد: ' . json_encode($depositResult));
                        }
                    } elseif ($invoice->remaining_amount < 0) {
                        // المورد مدين للشركة (رصيد المورد يقل)
                        $withdrawResult = $supplier->withdraw(abs($invoice->remaining_amount), $userCashBoxId);
                        if ($withdrawResult !== true) {
                            throw new \Exception('فشل سحب مبلغ زائد من رصيد المورد: ' . json_encode($withdrawResult));
                        }
                    }
                } else {
                    Log::warning('PurchaseInvoiceService: لم يتم العثور على المورد (إنشاء).', ['supplier_user_id' => $invoice->user_id]);
                }
            }

            // تسجيل عملية الإنشاء
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
     * @param array $data البيانات الجديدة للفاتورة.
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('PurchaseInvoiceService: بدء تحديث فاتورة شراء.', ['invoice_id' => $invoice->id, 'data' => $data]);

            // جلب القيم الأصلية للفاتورة من DB قبل أي تعديل
            $freshInvoice = Invoice::find($invoice->id);
            if (!$freshInvoice) {
                throw new \Exception("فشل العثور على الفاتورة (ID: {$invoice->id}) أثناء التحديث.");
            }
            $oldPaidAmount = $freshInvoice->paid_amount;
            $oldRemainingAmount = $freshInvoice->remaining_amount;

            // خصم المخزون للعناصر القديمة (عكس عملية الشراء الأصلية)
            $this->decrementStockForInvoiceItems($invoice);

            // تحديث بيانات الفاتورة الرئيسية
            $this->updateInvoice($invoice, $data);

            // حساب الفروقات ومعالجة الأرصدة
            $newPaidAmount = $data['paid_amount'] ?? 0;
            $paidAmountDifference = $newPaidAmount - $oldPaidAmount;

            $newRemainingAmount = $invoice->remaining_amount;
            $remainingAmountDifference = $newRemainingAmount - $oldRemainingAmount;

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null; // user_cash_box_id هنا تخص المورد

            // معالجة رصيد الموظف (الخزنة) - الشركة تدفع للمورد
            if ($paidAmountDifference !== 0) {
                if ($paidAmountDifference > 0) {
                    // تم دفع مبلغ إضافي للمورد، يتم سحبه من خزنة الموظف
                    $withdrawResult = $authUser->withdraw(abs($paidAmountDifference), $cashBoxId);
                    if ($withdrawResult !== true) {
                        throw new \Exception('فشل سحب المبلغ الإضافي من خزنة الموظف: ' . json_encode($withdrawResult));
                    }
                } else {
                    // تم استرجاع مبلغ من المورد، يتم إيداعه في خزنة الموظف
                    $depositResult = $authUser->deposit(abs($paidAmountDifference), $cashBoxId);
                    if ($depositResult !== true) {
                        throw new \Exception('فشل إيداع المبلغ المسترجع في خزنة الموظف: ' . json_encode($depositResult));
                    }
                }
            }

            // معالجة رصيد المورد
            if ($invoice->user_id) { // user_id في فاتورة الشراء هو معرف المورد
                $supplier = User::find($invoice->user_id);
                if ($supplier) {
                    if ($remainingAmountDifference > 0) {
                        // زاد المبلغ المتبقي (زاد دين الشركة للمورد)، رصيد المورد يزيد
                        $depositResult = $supplier->deposit(abs($remainingAmountDifference), $userCashBoxId);
                        if ($depositResult !== true) {
                            throw new \Exception('فشل إيداع مبلغ متبقي إضافي في رصيد المورد: ' . json_encode($depositResult));
                        }
                    } elseif ($remainingAmountDifference < 0) {
                        // نقص المبلغ المتبقي (نقص دين الشركة للمورد)، رصيد المورد يقل
                        $withdrawResult = $supplier->withdraw(abs($remainingAmountDifference), $userCashBoxId);
                        if ($withdrawResult !== true) {
                            throw new \Exception('فشل سحب مبلغ سداد دين/فائض من رصيد المورد: ' . json_encode($withdrawResult));
                        }
                    }
                } else {
                    Log::warning('PurchaseInvoiceService: لم يتم العثور على المورد أثناء تحديث الرصيد.', ['supplier_user_id' => $invoice->user_id]);
                }
            }

            // التحقق من المنتجات والمتغيرات الجديدة
            foreach ($data['items'] as $item) {
                $variant = ProductVariant::find($item['variant_id']);
                if (!$variant) {
                    throw ValidationException::withMessages([
                        'variant_id' => ['المتغير بمعرف ' . $item['variant_id'] . ' غير موجود.'],
                    ]);
                }
            }

            // مزامنة بنود الفاتورة
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);

            // زيادة المخزون للبنود الجديدة/المحدثة
            $this->incrementStockForItems($data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);

            // تسجيل عملية التحديث
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
     * @throws \Exception إذا كانت الفاتورة مدفوعة بالكامل.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            if ($invoice->status === 'paid') {
                throw new \Exception('لا يمكن إلغاء فاتورة مدفوعة بالكامل.');
            }

            // خصم الكمية من المخزون (عكس عملية الشراء)
            $this->decrementStockForInvoiceItems($invoice);

            // تغيير حالة الفاتورة
            $invoice->update(['status' => 'canceled']);

            // حذف البنود
            $this->deleteInvoiceItems($invoice);

            // معالجة الرصيد المالي للموظفين والموردين
            $authUser = Auth::user();
            $cashBoxId = null; // قد تحتاج لتمريرها في الـ data أو جلبها بطريقة أخرى
            $userCashBoxId = null; // قد تحتاج لتمريرها في الـ data أو جلبها بطريقة أخرى

            // عكس المبلغ المدفوع من الشركة للمورد
            if ($invoice->paid_amount > 0) {
                // المبلغ الذي دفعته الشركة للمورد يجب أن يعود إلى خزنة الشركة
                $depositResult = $authUser->deposit($invoice->paid_amount, $cashBoxId);
                if ($depositResult !== true) {
                    Log::error('PurchaseInvoiceService: فشل إيداع مبلغ مدفوع مسترجع في رصيد الموظف (إلغاء).', ['result' => $depositResult]);
                }
            }

            // عكس المبلغ المتبقي (دين الشركة للمورد)
            if ($invoice->user_id) { // user_id في فاتورة الشراء هو معرف المورد
                $supplier = User::find($invoice->user_id);
                if ($supplier) {
                    if ($invoice->remaining_amount > 0) {
                        // الشركة كانت مدينة للمورد، الآن يتم إلغاء الدين (سحب من رصيد المورد)
                        $withdrawResult = $supplier->withdraw($invoice->remaining_amount, $userCashBoxId);
                        if ($withdrawResult !== true) {
                            Log::error('PurchaseInvoiceService: فشل سحب مبلغ متبقي من رصيد المورد (إلغاء).', ['result' => $withdrawResult]);
                        }
                    } elseif ($invoice->remaining_amount < 0) {
                        // المورد كان مديناً للشركة، الآن يتم إلغاء الدين (إيداع في رصيد المورد)
                        $depositResult = $supplier->deposit(abs($invoice->remaining_amount), $userCashBoxId);
                        if ($depositResult !== true) {
                            Log::error('PurchaseInvoiceService: فشل إيداع مبلغ دين المورد الملغى في رصيد المورد (إلغاء).', ['result' => $depositResult]);
                        }
                    }
                } else {
                    Log::warning('PurchaseInvoiceService: لم يتم العثور على المورد أثناء الإلغاء.', ['supplier_user_id' => $invoice->user_id]);
                }
            }

            $invoice->logCanceled('إلغاء فاتورة شراء رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في إلغاء فاتورة الشراء.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

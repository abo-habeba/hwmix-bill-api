<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use App\Services\UserSelfDebtService;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log; // تم التصحيح

class PurchaseInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    /**
     * إنشاء فاتورة شراء جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws ValidationException
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            // 1. التحقق من المنتجات والمتغيرات
            foreach ($data['items'] as $item) {
                $variant = ProductVariant::find($item['variant_id']);
                if (!$variant) {
                    throw ValidationException::withMessages([
                        'variant_id' => ['المتغير بمعرف ' . $item['variant_id'] . ' غير موجود.'],
                    ]);
                }
            }

            // 2. إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            // 3. إنشاء بنود الفاتورة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            // 4. زيادة الكمية في المخزون للبنود المشتراة
            $this->incrementStockForItems($data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            // 5. تسجيل عملية الإنشاء في سجل النشاط
            $invoice->logCreated('إنشاء فاتورة شراء رقم ' . $invoice->invoice_number);

            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;
            $authUser = Auth::user();

            // 6. خصم المبلغ المدفوع من رصيد المستخدم الحالي (الشركة/الموظف الذي قام بالشراء)
            if ($invoice->paid_amount > 0) {
                Log::info('PurchaseInvoiceService: سحب مبلغ مدفوع من رصيد الموظف.', [
                    'user_id' => $authUser->id,
                    'amount' => $invoice->paid_amount,
                    'cash_box_id' => $cashBoxId
                ]);
                $withdrawResult = $authUser->withdraw($invoice->paid_amount, $cashBoxId);
                if ($withdrawResult !== true) {
                    throw new \Exception('فشل سحب المبلغ المدفوع من خزنة الموظف: ' . json_encode($withdrawResult));
                }
                Log::info('PurchaseInvoiceService: تم سحب المبلغ المدفوع من رصيد الموظف.', [
                    'user_id' => $authUser->id,
                    'amount' => $invoice->paid_amount,
                    'result' => $withdrawResult
                ]);
            }

            // 7. معالجة رصيد المورد بناءً على المبلغ المتبقي في الفاتورة
            Log::info('PurchaseInvoiceService: التحقق من إضافة/خصم المبلغ المتبقي للمورد (إنشاء).', [
                'invoice_user_id' => $invoice->user_id,
                'auth_user_id' => $authUser->id,
                'remaining_amount' => $invoice->remaining_amount
            ]);

            if ($invoice->user_id && $invoice->user_id != $authUser->id && $invoice->remaining_amount !== 0) {
                $supplier = User::find($invoice->user_id);
                if ($supplier) {
                    if ($invoice->remaining_amount > 0) {
                        // الشركة مدينة للمورد (رصيد المورد موجب)
                        Log::info('PurchaseInvoiceService: إيداع مبلغ متبقي في رصيد المورد (إنشاء).', [
                            'supplier_id' => $supplier->id,
                            'amount' => $invoice->remaining_amount,
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $depositResult = $supplier->deposit($invoice->remaining_amount, $userCashBoxId);
                        Log::info('PurchaseInvoiceService: تم إيداع المبلغ المتبقي للمورد (إنشاء).', [
                            'supplier_id' => $supplier->id,
                            'amount' => $invoice->remaining_amount,
                            'result' => $depositResult
                        ]);
                    } else {
                        // المورد مدين للشركة (رصيد المورد سالب)
                        Log::info('PurchaseInvoiceService: سحب مبلغ زائد من رصيد المورد (إنشاء).', [
                            'supplier_id' => $supplier->id,
                            'amount' => abs($invoice->remaining_amount),
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $withdrawResult = $supplier->withdraw(abs($invoice->remaining_amount), $userCashBoxId);
                        Log::info('PurchaseInvoiceService: تم سحب المبلغ الزائد من رصيد المورد (إنشاء).', [
                            'supplier_id' => $supplier->id,
                            'amount' => abs($invoice->remaining_amount),
                            'result' => $withdrawResult
                        ]);
                    }
                } else {
                    Log::warning('PurchaseInvoiceService: لم يتم العثور على المورد (إنشاء).', ['supplier_user_id' => $invoice->user_id]);
                }
            } else {
                Log::info('PurchaseInvoiceService: لم يتم استيفاء شروط تعديل رصيد المورد (إنشاء).', [
                    'invoice_user_id' => $invoice->user_id,
                    'auth_user_id' => $authUser->id,
                    'remaining_amount' => $invoice->remaining_amount
                ]);
            }

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
            Log::info('PurchaseInvoiceService: بدء تحديث الفاتورة - مراجعة المبالغ.', [
                'old_paid_amount' => $invoice->getOriginal('paid_amount'),
                'new_paid_amount_from_data' => $data['paid_amount'] ?? 0,
                'calculated_paid_difference' => ($data['paid_amount'] ?? 0) - $invoice->getOriginal('paid_amount'),
                'old_remaining_amount' => $invoice->getOriginal('remaining_amount'),
                'new_remaining_amount_from_data' => $data['remaining_amount'] ?? 0,
                'calculated_remaining_difference' => ($data['remaining_amount'] ?? 0) - $invoice->getOriginal('remaining_amount'),
                'invoice_id' => $invoice->id,
                'user_id' => Auth::user()->id,
                'supplier_user_id' => $invoice->user_id,
            ]);

            // 1. خصم المخزون للعناصر القديمة في الفاتورة (عكس عملية الشراء الأصلية)
            $this->decrementStockForInvoiceItems($invoice);
            Log::info('PurchaseInvoiceService: تم خصم المخزون للعناصر القديمة.');

            // 2. معالجة التغيرات المالية (المبالغ المدفوعة من الشركة للمورد)
            $oldPaidAmount = $invoice->getOriginal('paid_amount'); // الحصول على القيمة الأصلية
            $newPaidAmount = $data['paid_amount'] ?? 0;
            $paidAmountDifference = $newPaidAmount - $oldPaidAmount;

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;

            if ($paidAmountDifference !== 0) {
                if ($paidAmountDifference > 0) {
                    // تم دفع مبلغ إضافي للمورد، يتم سحبه من خزنة الموظف
                    Log::info('PurchaseInvoiceService: سحب مبلغ إضافي من رصيد الموظف (تحديث).', [
                        'user_id' => $authUser->id,
                        'amount' => abs($paidAmountDifference),
                        'cash_box_id' => $cashBoxId
                    ]);
                    $withdrawResult = $authUser->withdraw(abs($paidAmountDifference), $cashBoxId);
                    if ($withdrawResult !== true) {
                        throw new \Exception('فشل سحب المبلغ الإضافي من خزنة الموظف: ' . json_encode($withdrawResult));
                    }
                    Log::info('PurchaseInvoiceService: تم سحب المبلغ الإضافي من رصيد الموظف (تحديث).', [
                        'user_id' => $authUser->id,
                        'amount' => abs($paidAmountDifference),
                        'result' => $withdrawResult
                    ]);
                } else {
                    // تم استرجاع مبلغ من المورد (أو تصحيح)، يتم إيداعه في خزنة الموظف
                    Log::info('PurchaseInvoiceService: إيداع مبلغ مسترجع في رصيد الموظف (تحديث).', [
                        'user_id' => $authUser->id,
                        'amount' => abs($paidAmountDifference),
                        'cash_box_id' => $cashBoxId
                    ]);
                    $depositResult = $authUser->deposit(abs($paidAmountDifference), $cashBoxId);
                    if ($depositResult !== true) {
                        throw new \Exception('فشل إيداع المبلغ المسترجع في خزنة الموظف: ' . json_encode($depositResult));
                    }
                    Log::info('PurchaseInvoiceService: تم إيداع المبلغ المسترجع في رصيد الموظف (تحديث).', [
                        'user_id' => $authUser->id,
                        'amount' => abs($paidAmountDifference),
                        'result' => $depositResult
                    ]);
                }
            }

            // 3. تحديث بيانات الفاتورة الرئيسية
            $this->updateInvoice($invoice, $data);
            Log::info('PurchaseInvoiceService: تم تحديث بيانات الفاتورة الرئيسية.');

            // 4. التحقق من المنتجات والمتغيرات الجديدة (للتأكد من وجودها)
            foreach ($data['items'] as $item) {
                $variant = ProductVariant::find($item['variant_id']);
                if (!$variant) {
                    throw ValidationException::withMessages([
                        'variant_id' => ['المتغير بمعرف ' . $item['variant_id'] . ' غير موجود.'],
                    ]);
                }
            }
            Log::info('PurchaseInvoiceService: تم التحقق من المنتجات والمتغيرات الجديدة.');


            // 5. مزامنة بنود الفاتورة (تحديث/إضافة/حذف)
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);
            Log::info('PurchaseInvoiceService: تم مزامنة بنود الفاتورة.');

            // 6. زيادة المخزون للبنود الجديدة/المحدثة
            $this->incrementStockForItems($data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);
            Log::info('PurchaseInvoiceService: تم زيادة المخزون للبنود الجديدة/المحدثة.');

            // 7. معالجة الرصيد المتبقي للمورد (دين الشركة للمورد)
            $oldRemainingAmount = $invoice->getOriginal('remaining_amount');
            $newRemainingAmount = $invoice->remaining_amount;

            $remainingAmountDifference = $newRemainingAmount - $oldRemainingAmount;

            // إذا كان المورد مستخدمًا آخر غير الموظف الحالي
            if ($invoice->user_id && $invoice->user_id != $authUser->id && $remainingAmountDifference !== 0) {
                $supplier = User::find($invoice->user_id);
                if ($supplier) {
                    $userCashBoxId = $data['user_cash_box_id'] ?? null;

                    if ($remainingAmountDifference > 0) {
                        // زاد المبلغ المتبقي (زاد دين الشركة للمورد)، رصيد المورد يصبح أكثر إيجابية
                        Log::info('PurchaseInvoiceService: إيداع مبلغ متبقي إضافي في رصيد المورد (تحديث).', [
                            'supplier_id' => $supplier->id,
                            'amount' => abs($remainingAmountDifference),
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $depositResult = $supplier->deposit(abs($remainingAmountDifference), $userCashBoxId);
                        Log::info('PurchaseInvoiceService: تم إيداع مبلغ متبقي إضافي في رصيد المورد (تحديث).', [
                            'supplier_id' => $supplier->id,
                            'amount' => abs($remainingAmountDifference),
                            'result' => $depositResult
                        ]);
                    } else { // $remainingAmountDifference < 0
                        // نقص المبلغ المتبقي (نقص دين الشركة للمورد)، المورد أصبح مديناً للشركة أو قل دينه للشركة، رصيد المورد يصبح أكثر سلبية
                        Log::info('PurchaseInvoiceService: سحب مبلغ سداد دين/فائض من رصيد المورد (تحديث).', [
                            'supplier_id' => $supplier->id,
                            'amount' => abs($remainingAmountDifference),
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $withdrawResult = $supplier->withdraw(abs($remainingAmountDifference), $userCashBoxId);
                        Log::info('PurchaseInvoiceService: تم سحب مبلغ سداد دين/فائض من رصيد المورد (تحديث).', [
                            'supplier_id' => $supplier->id,
                            'amount' => abs($remainingAmountDifference),
                            'result' => $withdrawResult
                        ]);
                    }
                } else {
                    Log::warning('PurchaseInvoiceService: لم يتم العثور على المورد أثناء تحديث الرصيد.', ['supplier_user_id' => $invoice->user_id]);
                }
            }

            // 8. تسجيل عملية التحديث في سجل النشاط
            $invoice->logUpdated('تحديث فاتورة شراء رقم ' . $invoice->invoice_number);
            Log::info('PurchaseInvoiceService: تم تحديث فاتورة الشراء بنجاح.', ['invoice_id' => $invoice->id]);

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
            // 1️⃣ تحقق من إمكانية الإلغاء
            if ($invoice->status === 'paid') {
                throw new \Exception('لا يمكن إلغاء فاتورة مدفوعة بالكامل.');
            }

            // 2️⃣ خصم الكمية من المخزون (عكس عملية الشراء)
            $this->decrementStockForInvoiceItems($invoice);

            // 3️⃣ تغيير حالة الفاتورة
            $invoice->update([
                'status' => 'canceled',
            ]);

            // 4️⃣ حذف البنود (اختياري)
            $this->deleteInvoiceItems($invoice);

            // 5️⃣ معالجة الرصيد المالي للمستخدمين (عكس المدفوعات والديون)
            $authUser = Auth::user();
            $cashBoxId = null;
            $userCashBoxId = null;

            // عكس المبلغ المدفوع من الشركة للمورد
            if ($invoice->paid_amount > 0) {
                // المبلغ الذي دفعته الشركة للمورد يجب أن يعود إلى خزنة الشركة
                Log::info('PurchaseInvoiceService: إيداع مبلغ مدفوع مسترجع في رصيد الموظف (إلغاء).', [
                    'user_id' => $authUser->id,
                    'amount' => $invoice->paid_amount,
                    'cash_box_id' => $cashBoxId
                ]);
                $depositResult = $authUser->deposit($invoice->paid_amount, $cashBoxId);
                if ($depositResult !== true) {
                    // لا يوجد سجل خطأ
                }
                Log::info('PurchaseInvoiceService: تم إيداع مبلغ مدفوع مسترجع في رصيد الموظف (إلغاء).', [
                    'user_id' => $authUser->id,
                    'amount' => $invoice->paid_amount,
                    'result' => $depositResult
                ]);
            }

            // عكس المبلغ المتبقي (دين الشركة للمورد)
            if ($invoice->user_id && $invoice->user_id != $authUser->id && $invoice->remaining_amount !== 0) {
                $supplier = User::find($invoice->user_id);
                if ($supplier) {
                    if ($invoice->remaining_amount > 0) {
                        // الشركة كانت مدينة للمورد، الآن يتم إلغاء الدين (سحب من رصيد المورد)
                        Log::info('PurchaseInvoiceService: سحب مبلغ متبقي من رصيد المورد (إلغاء).', [
                            'supplier_id' => $supplier->id,
                            'amount' => $invoice->remaining_amount,
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $withdrawResult = $supplier->withdraw($invoice->remaining_amount, $userCashBoxId);
                        Log::info('PurchaseInvoiceService: تم سحب مبلغ متبقي من رصيد المورد (إلغاء).', [
                            'supplier_id' => $supplier->id,
                            'amount' => $invoice->remaining_amount,
                            'result' => $withdrawResult
                        ]);
                    } else {
                        // المورد كان مديناً للشركة، الآن يتم إلغاء الدين (إيداع في رصيد المورد)
                        Log::info('PurchaseInvoiceService: إيداع مبلغ دين المورد الملغى في رصيد المورد (إلغاء).', [
                            'supplier_id' => $supplier->id,
                            'amount' => abs($invoice->remaining_amount),
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $depositResult = $supplier->deposit(abs($invoice->remaining_amount), $userCashBoxId);
                        Log::info('PurchaseInvoiceService: تم إيداع مبلغ دين المورد الملغى في رصيد المورد (إلغاء).', [
                            'supplier_id' => $supplier->id,
                            'amount' => abs($invoice->remaining_amount),
                            'result' => $depositResult
                        ]);
                    }
                } else {
                    Log::warning('PurchaseInvoiceService: لم يتم العثور على المورد أثناء الإلغاء.', ['supplier_user_id' => $invoice->user_id]);
                }
            }

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('PurchaseInvoiceService: فشل في إلغاء فاتورة الشراء.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

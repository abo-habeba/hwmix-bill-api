<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;
use App\Services\InstallmentService;
use App\Services\UserSelfDebtService;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Invoice\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

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

            // التحقق من توفر المخزون قبل إنشاء الفاتورة
            $this->checkVariantsStock($data['items']);

            // إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            // إنشاء بنود الفاتورة
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            // خصم المخزون للبنود التي تم بيعها
            $this->deductStockForItems($data['items']);

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // معالجة الرصيد بناءً على المبلغ المدفوع والمتبقي
            Log::info('SaleInvoiceService: معالجة رصيد العميل (إنشاء).', [
                'invoice_user_id' => $invoice->user_id,
                'auth_user_id' => $authUser->id,
                'paid_amount' => $invoice->paid_amount,
                'remaining_amount' => $invoice->remaining_amount
            ]);

            if ($invoice->user_id && $invoice->user_id == $authUser->id) {
                // إذا كان المستخدم هو نفسه المشتري (فاتورة ذاتية)
                app(UserSelfDebtService::class)->registerPurchase(
                    $authUser,
                    $invoice->paid_amount,
                    $invoice->remaining_amount,
                    $cashBoxId,
                    $invoice->company_id
                );
                Log::info('SaleInvoiceService: تم تسجيل فاتورة ذاتية.', [
                    'user_id' => $authUser->id,
                    'paid' => $invoice->paid_amount,
                    'remaining' => $invoice->remaining_amount
                ]);
            } elseif ($invoice->user_id && $invoice->user_id != $authUser->id) {
                // إذا كان المشتري مستخدمًا آخر
                $buyer = User::find($invoice->user_id);
                if ($buyer) {
                    if ($invoice->paid_amount > 0) {
                        // إيداع المبلغ المدفوع في خزنة الموظف البائع
                        Log::info('SaleInvoiceService: إيداع المبلغ المدفوع في خزنة البائع.', [
                            'seller_id' => $authUser->id,
                            'amount' => $invoice->paid_amount,
                            'cash_box_id' => $cashBoxId
                        ]);
                        $depositResult = $authUser->deposit($invoice->paid_amount, $cashBoxId);
                        Log::info('SaleInvoiceService: تم إيداع المبلغ المدفوع في خزنة البائع.', ['result' => $depositResult]);
                    }

                    // معالجة الرصيد المتبقي للعميل
                    if ($invoice->remaining_amount > 0) {
                        // العميل مدين للشركة (رصيد العميل سالب)
                        Log::info('SaleInvoiceService: سحب مبلغ متبقي من رصيد العميل (دين).', [
                            'buyer_id' => $buyer->id,
                            'amount' => $invoice->remaining_amount,
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $withdrawResult = $buyer->withdraw($invoice->remaining_amount, $userCashBoxId);
                        Log::info('SaleInvoiceService: تم سحب مبلغ متبقي من رصيد العميل.', ['result' => $withdrawResult]);
                    } elseif ($invoice->remaining_amount < 0) {
                        // الشركة مدينة للعميل (العميل دفع زيادة، رصيد العميل موجب)
                        Log::info('SaleInvoiceService: إيداع مبلغ زائد في رصيد العميل (دفع زائد).', [
                            'buyer_id' => $buyer->id,
                            'amount' => abs($invoice->remaining_amount),
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $depositResult = $buyer->deposit(abs($invoice->remaining_amount), $userCashBoxId);
                        Log::info('SaleInvoiceService: تم إيداع مبلغ زائد في رصيد العميل.', ['result' => $depositResult]);
                    }
                } else {
                    Log::warning('SaleInvoiceService: لم يتم العثور على العميل (إنشاء).', ['buyer_user_id' => $invoice->user_id]);
                }
            } else {
                Log::info('SaleInvoiceService: لم يتم استيفاء شروط تعديل رصيد العميل (إنشاء).', [
                    'invoice_user_id' => $invoice->user_id,
                    'auth_user_id' => $authUser->id,
                    'remaining_amount' => $invoice->remaining_amount
                ]);
            }

            // إنشاء خطط الأقساط إذا كانت موجودة في البيانات
            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
                Log::info('SaleInvoiceService: تم إنشاء خطة أقساط.', ['invoice_id' => $invoice->id]);
            }
            // تسجيل عملية الإنشاء في سجل النشاط
            $invoice->logCreated('إنشاء فاتورة بيع رقم ' . $invoice->invoice_number);
            Log::info('SaleInvoiceService: تم إنشاء فاتورة البيع بنجاح.', ['invoice_id' => $invoice->id]);

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
            Log::info('SaleInvoiceService: حالة الفاتورة قبل التحديث.', [
                'old_paid_amount' => $invoice->getOriginal('paid_amount'),
                'old_remaining_amount' => $invoice->getOriginal('remaining_amount'),
                'new_paid_amount_from_data' => $data['paid_amount'] ?? 0,
                'new_remaining_amount_from_data' => $data['remaining_amount'] ?? 0,
            ]);

            // 1. استرجاع المخزون للعناصر القديمة في الفاتورة
            // هذا ضروري قبل مزامنة البنود الجديدة لضمان عدم وجود تداخل في الكميات
            $this->returnStockForItems($invoice);
            Log::info('SaleInvoiceService: تم استرجاع المخزون للعناصر القديمة.');

            // 2. إلغاء خطط الأقساط القديمة المرتبطة بالفاتورة
            if ($invoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($invoice);
                Log::info('SaleInvoiceService: تم إلغاء خطة الأقساط القديمة.');
            }

            // 3. معالجة التغيرات المالية (المبالغ المدفوعة)
            $oldPaidAmount = $invoice->getOriginal('paid_amount');
            $newPaidAmount = $data['paid_amount'] ?? 0;
            $paidAmountDifference = $newPaidAmount - $oldPaidAmount;

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            if ($paidAmountDifference !== 0) {
                if ($paidAmountDifference > 0) {
                    // تم دفع مبلغ إضافي، يتم إيداعه في خزنة الموظف البائع
                    Log::info('SaleInvoiceService: إيداع مبلغ إضافي في خزنة البائع (تحديث).', [
                        'seller_id' => $authUser->id,
                        'amount' => abs($paidAmountDifference),
                        'cash_box_id' => $cashBoxId
                    ]);
                    $depositResult = $authUser->deposit(abs($paidAmountDifference), $cashBoxId);
                    if ($depositResult !== true) {
                        throw new \Exception('فشل إيداع المبلغ الإضافي في خزنة الموظف: ' . json_encode($depositResult));
                    }
                    Log::info('SaleInvoiceService: تم إيداع مبلغ إضافي في خزنة البائع (تحديث).', ['result' => $depositResult]);
                } else {
                    // تم سحب مبلغ (أو استرجاع)، يتم سحبه من خزنة الموظف البائع
                    Log::info('SaleInvoiceService: سحب مبلغ من خزنة البائع (تحديث).', [
                        'seller_id' => $authUser->id,
                        'amount' => abs($paidAmountDifference),
                        'cash_box_id' => $cashBoxId
                    ]);
                    $withdrawResult = $authUser->withdraw(abs($paidAmountDifference), $cashBoxId);
                    if ($withdrawResult !== true) {
                        throw new \Exception('فشل سحب المبلغ من خزنة الموظف: ' . json_encode($withdrawResult));
                    }
                    Log::info('SaleInvoiceService: تم سحب مبلغ من خزنة البائع (تحديث).', ['result' => $withdrawResult]);
                }
            }

            // 4. تحديث بيانات الفاتورة الرئيسية
            $this->updateInvoice($invoice, $data);
            Log::info('SaleInvoiceService: تم تحديث بيانات الفاتورة الرئيسية.');

            // 5. التحقق من مخزون المتغيرات للبنود الجديدة
            $this->checkVariantsStock($data['items']);
            Log::info('SaleInvoiceService: تم التحقق من مخزون المتغيرات للبنود الجديدة.');

            // 6. مزامنة بنود الفاتورة (تحديث/إضافة/حذف)
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);
            Log::info('SaleInvoiceService: تم مزامنة بنود الفاتورة.');

            // 7. خصم المخزون للبنود الجديدة/المحدثة
            $this->deductStockForItems($data['items']);
            Log::info('SaleInvoiceService: تم خصم المخزون للبنود الجديدة/المحدثة.');

            // 8. معالجة الرصيد المتبقي للمستخدم (المدين/الدائن)
            $oldRemainingAmount = $invoice->getOriginal('remaining_amount');
            $newRemainingAmount = $invoice->remaining_amount;

            $remainingAmountDifference = $newRemainingAmount - $oldRemainingAmount;

            Log::info('SaleInvoiceService: معالجة رصيد العميل (تحديث).', [
                'invoice_user_id' => $invoice->user_id,
                'auth_user_id' => $authUser->id,
                'old_remaining_amount' => $oldRemainingAmount,
                'new_remaining_amount' => $newRemainingAmount,
                'remaining_amount_difference' => $remainingAmountDifference
            ]);

            if ($invoice->user_id && $invoice->user_id == $authUser->id) {
                // المستخدم هو نفسه الذي قام بالشراء (فاتورة ذاتية)
                if ($remainingAmountDifference > 0) {
                    // زاد المبلغ المتبقي (زاد الدين على المستخدم)
                    Log::info('SaleInvoiceService: تسجيل زيادة دين على المستخدم (فاتورة ذاتية).', ['amount' => abs($remainingAmountDifference)]);
                    app(UserSelfDebtService::class)->registerPurchase(
                        $authUser,
                        0, // لا يوجد دفع جديد هنا، فقط زيادة دين
                        abs($remainingAmountDifference),
                        $cashBoxId,
                        $invoice->company_id
                    );
                } elseif ($remainingAmountDifference < 0) {
                    // نقص المبلغ المتبقي (نقص الدين على المستخدم)
                    Log::info('SaleInvoiceService: تسجيل سداد دين من المستخدم (فاتورة ذاتية).', ['amount' => abs($remainingAmountDifference)]);
                    app(UserSelfDebtService::class)->registerPayment(
                        $authUser,
                        abs($remainingAmountDifference), // المبلغ الذي تم سداده من الدين
                        0, // لا يوجد دين متبقي جديد
                        $cashBoxId,
                        $invoice->company_id
                    );
                }
            } elseif ($invoice->user_id && $invoice->user_id != $authUser->id) {
                // المشتري مستخدم آخر
                $buyer = User::find($invoice->user_id);
                if ($buyer) {
                    if ($remainingAmountDifference > 0) {
                        // زاد المبلغ المتبقي على المشتري (زاد دينه)، رصيد العميل يصبح أكثر سلبية
                        Log::info('SaleInvoiceService: سحب مبلغ إضافي من رصيد العميل (زيادة دين).', [
                            'buyer_id' => $buyer->id,
                            'amount' => abs($remainingAmountDifference),
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $withdrawResult = $buyer->withdraw(abs($remainingAmountDifference), $userCashBoxId);
                        Log::info('SaleInvoiceService: تم سحب مبلغ إضافي من رصيد العميل.', ['result' => $withdrawResult]);
                    } elseif ($remainingAmountDifference < 0) {
                        // نقص المبلغ المتبقي على المشتري (سدد جزء من دينه أو دفع زيادة)، رصيد العميل يصبح أكثر إيجابية
                        Log::info('SaleInvoiceService: إيداع مبلغ في رصيد العميل (سداد دين/دفع زائد).', [
                            'buyer_id' => $buyer->id,
                            'amount' => abs($remainingAmountDifference),
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $depositResult = $buyer->deposit(abs($remainingAmountDifference), $userCashBoxId);
                        Log::info('SaleInvoiceService: تم إيداع مبلغ في رصيد العميل.', ['result' => $depositResult]);
                    }
                } else {
                    Log::warning('SaleInvoiceService: لم يتم العثور على العميل أثناء تحديث الرصيد.', ['buyer_user_id' => $invoice->user_id]);
                }
            }

            // 9. إنشاء أو تحديث خطة الأقساط الجديدة
            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
                Log::info('SaleInvoiceService: تم إنشاء/تحديث خطة أقساط.');
            }

            // 10. تسجيل عملية التحديث في سجل النشاط
            $invoice->logUpdated('تحديث فاتورة بيع رقم ' . $invoice->invoice_number);
            Log::info('SaleInvoiceService: تم تحديث فاتورة البيع بنجاح.', ['invoice_id' => $invoice->id]);

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
            // 1️⃣ تحقق من إمكانية الإلغاء
            if ($invoice->status === 'paid') {
                throw new \Exception('لا يمكن إلغاء فاتورة مدفوعة بالكامل.');
            }

            // 2️⃣ استرجاع الكمية للمخزون
            $this->returnStockForItems($invoice);
            Log::info('SaleInvoiceService: تم استرجاع المخزون للعناصر الملغاة.');

            // 3️⃣ تغيير حالة الفاتورة
            $invoice->update([
                'status' => 'canceled',
            ]);
            Log::info('SaleInvoiceService: تم تغيير حالة الفاتورة إلى ملغاة.');

            // 4️⃣ حذف البنود (اختياري حسب رؤيتك)
            $this->deleteInvoiceItems($invoice);
            Log::info('SaleInvoiceService: تم حذف بنود الفاتورة.');

            // 5️⃣ إلغاء خطط الأقساط المرتبطة بالفاتورة
            if ($invoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($invoice);
                Log::info('SaleInvoiceService: تم إلغاء خطط الأقساط المرتبطة بالفاتورة.');
            }

            // 6️⃣ معالجة الرصيد المالي للمستخدمين (إلغاء الدين أو استرجاع المدفوعات)
            $authUser = Auth::user();
            $cashBoxId = null;
            $userCashBoxId = null;

            Log::info('SaleInvoiceService: معالجة رصيد العميل (إلغاء).', [
                'invoice_user_id' => $invoice->user_id,
                'auth_user_id' => $authUser->id,
                'paid_amount' => $invoice->paid_amount,
                'remaining_amount' => $invoice->remaining_amount
            ]);

            if ($invoice->user_id && $invoice->user_id == $authUser->id) {
                // المستخدم هو نفسه الذي قام بالشراء (فاتورة ذاتية)
                // إذا كان هناك دين متبقي، يتم إلغاؤه (تسجيل دفعة للمستخدم)
                if ($invoice->remaining_amount > 0) {
                    Log::info('SaleInvoiceService: تسجيل إلغاء دين متبقي على المستخدم (فاتورة ذاتية).', ['amount' => $invoice->remaining_amount]);
                    app(UserSelfDebtService::class)->registerPayment(
                        $authUser,
                        $invoice->remaining_amount,
                        0,
                        $cashBoxId,
                        $invoice->company_id
                    );
                }
                // إذا كان هناك مبلغ مدفوع، يتم سحبه من خزنة المستخدم (افتراضًا أنه تم إيداعه عند الإنشاء)
                if ($invoice->paid_amount > 0) {
                    Log::info('SaleInvoiceService: سحب مبلغ مدفوع مسترجع من خزنة المستخدم (فاتورة ذاتية).', ['amount' => $invoice->paid_amount]);
                    $withdrawResult = $authUser->withdraw($invoice->paid_amount, $cashBoxId);
                    if ($withdrawResult !== true) {
                        // لا يوجد سجل خطأ
                    }
                    Log::info('SaleInvoiceService: تم سحب مبلغ مدفوع مسترجع من خزنة المستخدم (فاتورة ذاتية).', ['result' => $withdrawResult]);
                }
            } elseif ($invoice->user_id && $invoice->user_id != $authUser->id) {
                // المشتري مستخدم آخر
                $buyer = User::find($invoice->user_id);
                if ($buyer) {
                    // إذا كان هناك دين متبقي على المشتري (رصيده سالب)، يتم إلغاؤه (إيداع له)
                    if ($invoice->remaining_amount > 0) {
                        Log::info('SaleInvoiceService: إيداع مبلغ دين العميل الملغى في رصيد العميل (إلغاء).', ['amount' => $invoice->remaining_amount]);
                        $depositResult = $buyer->deposit($invoice->remaining_amount, $userCashBoxId);
                        Log::info('SaleInvoiceService: تم إيداع مبلغ دين العميل الملغى في رصيد العميل.', ['result' => $depositResult]);
                    }
                    // إذا كان هناك مبلغ زائد دفعه العميل (رصيده موجب)، يتم سحبه من رصيده
                    elseif ($invoice->remaining_amount < 0) {
                        Log::info('SaleInvoiceService: سحب مبلغ زائد مدفوع من رصيد العميل (إلغاء).', ['amount' => abs($invoice->remaining_amount)]);
                        $withdrawResult = $buyer->withdraw(abs($invoice->remaining_amount), $userCashBoxId);
                        Log::info('SaleInvoiceService: تم سحب مبلغ زائد مدفوع من رصيد العميل.', ['result' => $withdrawResult]);
                    }

                    // إذا كان هناك مبلغ مدفوع من المشتري، يتم سحبه من خزنة البائع (الموظف)
                    if ($invoice->paid_amount > 0) {
                        Log::info('SaleInvoiceService: سحب مبلغ مدفوع من خزنة البائع (إلغاء).', ['amount' => $invoice->paid_amount]);
                        $withdrawResult = $authUser->withdraw($invoice->paid_amount, $cashBoxId);
                        if ($withdrawResult !== true) {
                            // لا يوجد سجل خطأ
                        }
                        Log::info('SaleInvoiceService: تم سحب مبلغ مدفوع من خزنة البائع (إلغاء).', ['result' => $withdrawResult]);
                    }
                } else {
                    Log::warning('SaleInvoiceService: لم يتم العثور على العميل أثناء الإلغاء.', ['buyer_user_id' => $invoice->user_id]);
                }
            }

            Log::info('SaleInvoiceService: تم إلغاء فاتورة البيع بنجاح.', ['invoice_id' => $invoice->id]);
            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في إلغاء فاتورة البيع.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

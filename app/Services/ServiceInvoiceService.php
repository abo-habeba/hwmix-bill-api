<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant; // قد لا تكون ضرورية لفاتورة الخدمة، ولكن تم تضمينها كقالب
use App\Models\Stock; // قد لا تكون ضرورية لفاتورة الخدمة، ولكن تم تضمينها كقالب
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait; // تم التصحيح
use App\Services\UserSelfDebtService;
use App\Http\Resources\InvoiceResource; // تم التصحيح
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ServiceInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait; // افتراض أنك تستخدم نفس الـ Trait

    /**
     * إنشاء فاتورة خدمة جديدة.
     *
     * @param array $data بيانات الفاتورة.
     * @return Invoice الفاتورة التي تم إنشاؤها.
     * @throws ValidationException
     * @throws \Throwable
     */
    public function create(array $data): Invoice
    {
        try {
            Log::info('ServiceInvoiceService: بدء إنشاء فاتورة خدمة.', ['data' => $data]);

            // 1. إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            // 2. إنشاء بنود الفاتورة (خدمات)
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            // 3. تسجيل عملية الإنشاء في سجل النشاط
            $invoice->logCreated('إنشاء فاتورة خدمة رقم ' . $invoice->invoice_number);

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // 4. معالجة الرصيد بناءً على المبلغ المدفوع والمتبقي (من منظور فاتورة بيع خدمة)
            Log::info('ServiceInvoiceService: معالجة رصيد العميل (إنشاء).', [
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
                Log::info('ServiceInvoiceService: تم تسجيل فاتورة ذاتية.', [
                    'user_id' => $authUser->id,
                    'paid' => $invoice->paid_amount,
                    'remaining' => $invoice->remaining_amount
                ]);
            } elseif ($invoice->user_id && $invoice->user_id != $authUser->id) {
                // إذا كان المشتري مستخدمًا آخر (عميل)
                $buyer = User::find($invoice->user_id);
                if ($buyer) {
                    if ($invoice->paid_amount > 0) {
                        // إيداع المبلغ المدفوع في خزنة الموظف البائع
                        Log::info('ServiceInvoiceService: إيداع المبلغ المدفوع في خزنة البائع.', [
                            'seller_id' => $authUser->id,
                            'amount' => $invoice->paid_amount,
                            'cash_box_id' => $cashBoxId
                        ]);
                        $depositResult = $authUser->deposit($invoice->paid_amount, $cashBoxId);
                        Log::info('ServiceInvoiceService: تم إيداع المبلغ المدفوع في خزنة البائع.', ['result' => $depositResult]);
                    }

                    // معالجة الرصيد المتبقي للعميل
                    if ($invoice->remaining_amount > 0) {
                        // العميل مدين للشركة (رصيد العميل سالب)
                        Log::info('ServiceInvoiceService: سحب مبلغ متبقي من رصيد العميل (دين).', [
                            'buyer_id' => $buyer->id,
                            'amount' => $invoice->remaining_amount,
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $withdrawResult = $buyer->withdraw($invoice->remaining_amount, $userCashBoxId);
                        Log::info('ServiceInvoiceService: تم سحب مبلغ متبقي من رصيد العميل.', ['result' => $withdrawResult]);
                    } elseif ($invoice->remaining_amount < 0) {
                        // الشركة مدينة للعميل (العميل دفع زيادة، رصيد العميل موجب)
                        Log::info('ServiceInvoiceService: إيداع مبلغ زائد في رصيد العميل (دفع زائد).', [
                            'buyer_id' => $buyer->id,
                            'amount' => abs($invoice->remaining_amount),
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $depositResult = $buyer->deposit(abs($invoice->remaining_amount), $userCashBoxId);
                        Log::info('ServiceInvoiceService: تم إيداع مبلغ زائد في رصيد العميل.', ['result' => $depositResult]);
                    }
                } else {
                    Log::warning('ServiceInvoiceService: لم يتم العثور على العميل (إنشاء).', ['buyer_user_id' => $invoice->user_id]);
                }
            } else {
                Log::info('ServiceInvoiceService: لم يتم استيفاء شروط تعديل رصيد العميل (إنشاء).', [
                    'invoice_user_id' => $invoice->user_id,
                    'auth_user_id' => $authUser->id,
                    'remaining_amount' => $invoice->remaining_amount
                ]);
            }

            Log::info('ServiceInvoiceService: تم إنشاء فاتورة الخدمة بنجاح.', ['invoice_id' => $invoice->id]);
            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ServiceInvoiceService: فشل في إنشاء فاتورة الخدمة.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * تحديث فاتورة خدمة موجودة.
     *
     * @param array $data البيانات الجديدة للفاتورة.
     * @param Invoice $invoice الفاتورة المراد تحديثها.
     * @return Invoice الفاتورة المحدثة.
     * @throws \Throwable
     */
    public function update(array $data, Invoice $invoice): Invoice
    {
        try {
            Log::info('ServiceInvoiceService: بدء تحديث فاتورة خدمة.', ['invoice_id' => $invoice->id, 'data' => $data]);
            Log::info('ServiceInvoiceService: حالة الفاتورة قبل التحديث.', [
                'old_paid_amount' => $invoice->getOriginal('paid_amount'),
                'old_remaining_amount' => $invoice->getOriginal('remaining_amount'),
                'new_paid_amount_from_data' => $data['paid_amount'] ?? 0,
                'new_remaining_amount_from_data' => $data['remaining_amount'] ?? 0,
            ]);

            // 1. معالجة التغيرات المالية (المبالغ المدفوعة)
            $oldPaidAmount = $invoice->getOriginal('paid_amount');
            $newPaidAmount = $data['paid_amount'] ?? 0;
            $paidAmountDifference = $newPaidAmount - $oldPaidAmount;

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            if ($paidAmountDifference !== 0) {
                if ($paidAmountDifference > 0) {
                    // تم دفع مبلغ إضافي، يتم إيداعه في خزنة الموظف البائع
                    Log::info('ServiceInvoiceService: إيداع مبلغ إضافي في خزنة البائع (تحديث).', [
                        'seller_id' => $authUser->id,
                        'amount' => abs($paidAmountDifference),
                        'cash_box_id' => $cashBoxId
                    ]);
                    $depositResult = $authUser->deposit(abs($paidAmountDifference), $cashBoxId);
                    if ($depositResult !== true) {
                        throw new \Exception('فشل إيداع المبلغ الإضافي في خزنة الموظف: ' . json_encode($depositResult));
                    }
                    Log::info('ServiceInvoiceService: تم إيداع مبلغ إضافي في خزنة البائع (تحديث).', ['result' => $depositResult]);
                } else {
                    // تم سحب مبلغ (أو استرجاع)، يتم سحبه من خزنة الموظف البائع
                    Log::info('ServiceInvoiceService: سحب مبلغ من خزنة البائع (تحديث).', [
                        'seller_id' => $authUser->id,
                        'amount' => abs($paidAmountDifference),
                        'cash_box_id' => $cashBoxId
                    ]);
                    $withdrawResult = $authUser->withdraw(abs($paidAmountDifference), $cashBoxId);
                    if ($withdrawResult !== true) {
                        throw new \Exception('فشل سحب المبلغ من خزنة الموظف: ' . json_encode($withdrawResult));
                    }
                    Log::info('ServiceInvoiceService: تم سحب مبلغ من خزنة البائع (تحديث).', ['result' => $withdrawResult]);
                }
            }

            // 2. تحديث بيانات الفاتورة الرئيسية
            $this->updateInvoice($invoice, $data);
            Log::info('ServiceInvoiceService: تم تحديث بيانات الفاتورة الرئيسية.');

            // 3. مزامنة بنود الفاتورة (تحديث/إضافة/حذف)
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);
            Log::info('ServiceInvoiceService: تم مزامنة بنود الفاتورة.');

            // 4. معالجة الرصيد المتبقي للمستخدم (المدين/الدائن)
            $oldRemainingAmount = $invoice->getOriginal('remaining_amount');
            $newRemainingAmount = $invoice->remaining_amount;

            $remainingAmountDifference = $newRemainingAmount - $oldRemainingAmount;

            Log::info('ServiceInvoiceService: معالجة رصيد العميل (تحديث).', [
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
                    Log::info('ServiceInvoiceService: تسجيل زيادة دين على المستخدم (فاتورة ذاتية).', ['amount' => abs($remainingAmountDifference)]);
                    app(UserSelfDebtService::class)->registerPurchase(
                        $authUser,
                        0, // لا يوجد دفع جديد هنا، فقط زيادة دين
                        abs($remainingAmountDifference),
                        $cashBoxId,
                        $invoice->company_id
                    );
                } elseif ($remainingAmountDifference < 0) {
                    // نقص المبلغ المتبقي (نقص الدين على المستخدم)
                    Log::info('ServiceInvoiceService: تسجيل سداد دين من المستخدم (فاتورة ذاتية).', ['amount' => abs($remainingAmountDifference)]);
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
                        Log::info('ServiceInvoiceService: سحب مبلغ إضافي من رصيد العميل (زيادة دين).', [
                            'buyer_id' => $buyer->id,
                            'amount' => abs($remainingAmountDifference),
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $withdrawResult = $buyer->withdraw(abs($remainingAmountDifference), $userCashBoxId);
                        Log::info('ServiceInvoiceService: تم سحب مبلغ إضافي من رصيد العميل.', ['result' => $withdrawResult]);
                    } elseif ($remainingAmountDifference < 0) {
                        // نقص المبلغ المتبقي على المشتري (سدد جزء من دينه أو دفع زيادة)، رصيد العميل يصبح أكثر إيجابية
                        Log::info('ServiceInvoiceService: إيداع مبلغ في رصيد العميل (سداد دين/دفع زائد).', [
                            'buyer_id' => $buyer->id,
                            'amount' => abs($remainingAmountDifference),
                            'user_cash_box_id' => $userCashBoxId
                        ]);
                        $depositResult = $buyer->deposit(abs($remainingAmountDifference), $userCashBoxId);
                        Log::info('ServiceInvoiceService: تم إيداع مبلغ في رصيد العميل.', ['result' => $depositResult]);
                    }
                } else {
                    Log::warning('ServiceInvoiceService: لم يتم العثور على العميل أثناء تحديث الرصيد.', ['buyer_user_id' => $invoice->user_id]);
                }
            }

            // 5. تسجيل عملية التحديث في سجل النشاط
            $invoice->logUpdated('تحديث فاتورة خدمة رقم ' . $invoice->invoice_number);
            Log::info('ServiceInvoiceService: تم تحديث فاتورة الخدمة بنجاح.', ['invoice_id' => $invoice->id]);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ServiceInvoiceService: فشل في تحديث فاتورة الخدمة.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * إلغاء فاتورة خدمة.
     *
     * @param Invoice $invoice الفاتورة المراد إلغاؤها.
     * @return Invoice الفاتورة الملغاة.
     * @throws \Exception إذا كانت الفاتورة مدفوعة بالكامل.
     * @throws \Throwable
     */
    public function cancel(Invoice $invoice): Invoice
    {
        try {
            Log::info('ServiceInvoiceService: بدء إلغاء فاتورة خدمة.', ['invoice_id' => $invoice->id]);
            // 1️⃣ تحقق من إمكانية الإلغاء
            if ($invoice->status === 'paid') {
                throw new \Exception('لا يمكن إلغاء فاتورة مدفوعة بالكامل.');
            }

            // 2️⃣ تغيير حالة الفاتورة
            $invoice->update([
                'status' => 'canceled',
            ]);
            Log::info('ServiceInvoiceService: تم تغيير حالة الفاتورة إلى ملغاة.');

            // 3️⃣ حذف البنود (اختياري حسب رؤيتك)
            $this->deleteInvoiceItems($invoice);
            Log::info('ServiceInvoiceService: تم حذف بنود الفاتورة.');

            // 4️⃣ معالجة الرصيد المالي للمستخدمين (إلغاء الدين أو استرجاع المدفوعات)
            $authUser = Auth::user();
            $cashBoxId = null;
            $userCashBoxId = null;

            Log::info('ServiceInvoiceService: معالجة رصيد العميل (إلغاء).', [
                'invoice_user_id' => $invoice->user_id,
                'auth_user_id' => $authUser->id,
                'paid_amount' => $invoice->paid_amount,
                'remaining_amount' => $invoice->remaining_amount
            ]);

            if ($invoice->user_id && $invoice->user_id == $authUser->id) {
                // المستخدم هو نفسه الذي قام بالشراء (فاتورة ذاتية)
                // إذا كان هناك دين متبقي، يتم إلغاؤه (تسجيل دفعة للمستخدم)
                if ($invoice->remaining_amount > 0) {
                    Log::info('ServiceInvoiceService: تسجيل إلغاء دين متبقي على المستخدم (فاتورة ذاتية).', ['amount' => $invoice->remaining_amount]);
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
                    Log::info('ServiceInvoiceService: سحب مبلغ مدفوع مسترجع من خزنة المستخدم (فاتورة ذاتية).', ['amount' => $invoice->paid_amount]);
                    $withdrawResult = $authUser->withdraw($invoice->paid_amount, $cashBoxId);
                    if ($withdrawResult !== true) {
                        // لا يوجد سجل خطأ
                    }
                    Log::info('ServiceInvoiceService: تم سحب مبلغ مدفوع مسترجع من خزنة المستخدم (فاتورة ذاتية).', ['result' => $withdrawResult]);
                }
            } elseif ($invoice->user_id && $invoice->user_id != $authUser->id) {
                // المشتري مستخدم آخر
                $buyer = User::find($invoice->user_id);
                if ($buyer) {
                    // إذا كان هناك دين متبقي على المشتري (رصيده سالب)، يتم إلغاؤه (إيداع له)
                    if ($invoice->remaining_amount > 0) {
                        Log::info('ServiceInvoiceService: إيداع مبلغ دين العميل الملغى في رصيد العميل (إلغاء).', ['amount' => $invoice->remaining_amount]);
                        $depositResult = $buyer->deposit($invoice->remaining_amount, $userCashBoxId);
                        Log::info('ServiceInvoiceService: تم إيداع مبلغ دين العميل الملغى في رصيد العميل.', ['result' => $depositResult]);
                    }
                    // إذا كان هناك مبلغ زائد دفعه العميل (رصيده موجب)، يتم سحبه من رصيده
                    elseif ($invoice->remaining_amount < 0) {
                        Log::info('ServiceInvoiceService: سحب مبلغ زائد مدفوع من رصيد العميل (إلغاء).', ['amount' => abs($invoice->remaining_amount)]);
                        $withdrawResult = $buyer->withdraw(abs($invoice->remaining_amount), $userCashBoxId);
                        Log::info('ServiceInvoiceService: تم سحب مبلغ زائد مدفوع من رصيد العميل.', ['result' => $withdrawResult]);
                    }

                    // إذا كان هناك مبلغ مدفوع من المشتري، يتم سحبه من خزنة البائع (الموظف)
                    if ($invoice->paid_amount > 0) {
                        Log::info('ServiceInvoiceService: سحب مبلغ مدفوع من خزنة البائع (إلغاء).', ['amount' => $invoice->paid_amount]);
                        $withdrawResult = $authUser->withdraw($invoice->paid_amount, $cashBoxId);
                        if ($withdrawResult !== true) {
                            // لا يوجد سجل خطأ
                        }
                        Log::info('ServiceInvoiceService: تم سحب مبلغ مدفوع من خزنة البائع (إلغاء).', ['result' => $withdrawResult]);
                    }
                } else {
                    Log::warning('ServiceInvoiceService: لم يتم العثور على العميل أثناء الإلغاء.', ['buyer_user_id' => $invoice->user_id]);
                }
            }

            Log::info('ServiceInvoiceService: تم إلغاء فاتورة الخدمة بنجاح.', ['invoice_id' => $invoice->id]);
            return $invoice;
        } catch (\Throwable $e) {
            Log::error('ServiceInvoiceService: فشل في إلغاء فاتورة الخدمة.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

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

            $this->checkVariantsStock($data['items']);

            $invoice = $this->createInvoice($data);
            if (!$invoice || !$invoice->id) {
                throw new \Exception('فشل في إنشاء الفاتورة.');
            }

            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);

            $this->deductStockForItems($data['items']);

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // معالجة رصيد الموظفين والعملاء
            if ($invoice->user_id == $authUser->id) { // فاتورة ذاتية للموظف البائع
                // app(UserSelfDebtService::class)->registerPurchase(
                //     $authUser,
                //     $invoice->paid_amount,
                //     $invoice->remaining_amount,
                //     $cashBoxId,
                //     $invoice->company_id
                // );
            } elseif ($invoice->user_id) { // المشتري مستخدم آخر (عميل)
                $buyer = User::find($invoice->user_id);
                if ($buyer) {
                    if ($invoice->paid_amount > 0) {
                        $depositResult = $authUser->deposit($invoice->paid_amount, $cashBoxId);
                        if ($depositResult !== true) {
                            throw new \Exception('فشل إيداع المبلغ المدفوع في خزنة البائع: ' . json_encode($depositResult));
                        }
                    }

                    if ($invoice->remaining_amount > 0) {
                        $withdrawResult = $buyer->withdraw($invoice->remaining_amount, $userCashBoxId);
                        if ($withdrawResult !== true) {
                            throw new \Exception('فشل سحب مبلغ متبقي من رصيد العميل: ' . json_encode($withdrawResult));
                        }
                    } elseif ($invoice->remaining_amount < 0) {
                        $depositResult = $buyer->deposit(abs($invoice->remaining_amount), $userCashBoxId);
                        if ($depositResult !== true) {
                            throw new \Exception('فشل إيداع مبلغ زائد في رصيد العميل: ' . json_encode($depositResult));
                        }
                    }
                } else {
                    Log::warning('SaleInvoiceService: لم يتم العثور على العميل (إنشاء).', ['buyer_user_id' => $invoice->user_id]);
                }
            }

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

            $freshInvoice = Invoice::find($invoice->id);
            if (!$freshInvoice) {
                throw new \Exception("فشل العثور على الفاتورة (ID: {$invoice->id}) أثناء التحديث.");
            }
            $oldPaidAmount = $freshInvoice->paid_amount;
            $oldRemainingAmount = $freshInvoice->remaining_amount;

            $this->returnStockForItems($invoice);

            if ($invoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($invoice);
            }

            $this->updateInvoice($invoice, $data);

            $newPaidAmount = $data['paid_amount'] ?? 0;
            $paidAmountDifference = $newPaidAmount - $oldPaidAmount;

            $newRemainingAmount = $invoice->remaining_amount;
            $remainingAmountDifference = $newRemainingAmount - $oldRemainingAmount;

            $authUser = Auth::user();
            $cashBoxId = $data['cash_box_id'] ?? null;
            $userCashBoxId = $data['user_cash_box_id'] ?? null;

            // معالجة رصيد الموظف (الخزنة)
            if ($paidAmountDifference !== 0) {
                if ($paidAmountDifference > 0) {
                    $depositResult = $authUser->deposit(abs($paidAmountDifference), $cashBoxId);
                    if ($depositResult !== true) {
                        throw new \Exception('فشل إيداع المبلغ الإضافي في خزنة الموظف: ' . json_encode($depositResult));
                    }
                } else {
                    $withdrawResult = $authUser->withdraw(abs($paidAmountDifference), $cashBoxId);
                    if ($withdrawResult !== true) {
                        throw new \Exception('فشل سحب المبلغ من خزنة الموظف: ' . json_encode($withdrawResult));
                    }
                }
            }
            // معالجة رصيد المشتري
            if ($invoice->user_id == $authUser->id) { // المشتري هو الموظف نفسه
                if ($remainingAmountDifference > 0) {
                    // app(UserSelfDebtService::class)->registerPurchase(
                    //     $authUser,
                    //     0,
                    //     abs($remainingAmountDifference),
                    //     $cashBoxId,
                    //     $invoice->company_id
                    // );
                } elseif ($remainingAmountDifference < 0) {
                    // app(UserSelfDebtService::class)->registerPayment(
                    //     $authUser,
                    //     abs($remainingAmountDifference),
                    //     0,
                    //     $cashBoxId,
                    //     $invoice->company_id
                    // );
                }
                // المشتري عميل آخر
            }
            if ($invoice->user_id) {
                $buyer = User::find($invoice->user_id);
                if ($buyer) {
                    if ($remainingAmountDifference > 0) {
                        $withdrawResult = $buyer->withdraw(abs($remainingAmountDifference), $userCashBoxId);
                        if ($withdrawResult !== true) {
                            throw new \Exception('فشل سحب مبلغ إضافي من رصيد العميل: ' . json_encode($withdrawResult));
                        }
                    } elseif ($remainingAmountDifference < 0) {
                        $depositResult = $buyer->deposit(abs($remainingAmountDifference), $userCashBoxId);
                        if ($depositResult !== true) {
                            throw new \Exception('فشل إيداع مبلغ في رصيد العميل: ' . json_encode($depositResult));
                        }
                    }
                } else {
                    Log::warning('SaleInvoiceService: لم يتم العثور على العميل أثناء تحديث الرصيد.', ['buyer_user_id' => $invoice->user_id]);
                }
            }

            $this->checkVariantsStock($data['items']);
            $this->syncInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);
            $this->deductStockForItems($data['items']);

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
            // if ($invoice->status === 'paid') {
            //     throw new \Exception('لا يمكن إلغاء فاتورة مدفوعة بالكامل.');
            // }

            $this->returnStockForItems($invoice);
            $invoice->update(['status' => 'canceled']);
            $this->deleteInvoiceItems($invoice);

            // if ($invoice->installmentPlan) {
            //     app(InstallmentService::class)->cancelInstallments($invoice);
            // }

            $authUser = Auth::user();
            $cashBoxId = null;
            $userCashBoxId = null;

            // معالجة الرصيد المالي للموظفين والعملاء
            if ($invoice->user_id == $authUser->id) { // الفاتورة ذاتية للموظف
                if ($invoice->remaining_amount > 0) {
                    // app(UserSelfDebtService::class)->registerPayment(
                    //     $authUser,
                    //     $invoice->remaining_amount,
                    //     0,
                    //     $cashBoxId,
                    //     $invoice->company_id
                    // );
                }
                if ($invoice->paid_amount > 0) {
                    $withdrawResult = $authUser->withdraw($invoice->paid_amount, $cashBoxId);
                    if ($withdrawResult !== true) {
                        Log::error('SaleInvoiceService: فشل سحب مبلغ مدفوع مسترجع من خزنة الموظف (فاتورة ذاتية).', ['result' => $withdrawResult]);
                    }
                }
            } elseif ($invoice->user_id) { // المشتري عميل
                $buyer = User::find($invoice->user_id);
                if ($buyer) {
                    if ($invoice->remaining_amount > 0) {
                        $depositResult = $buyer->deposit($invoice->remaining_amount, $userCashBoxId);
                        if ($depositResult !== true) {
                            Log::error('SaleInvoiceService: فشل إيداع مبلغ دين العميل الملغى في رصيد العميل.', ['result' => $depositResult]);
                        }
                    } elseif ($invoice->remaining_amount < 0) {
                        $withdrawResult = $buyer->withdraw(abs($invoice->remaining_amount), $userCashBoxId);
                        if ($withdrawResult !== true) {
                            Log::error('SaleInvoiceService: فشل سحب مبلغ زائد مدفوع من رصيد العميل.', ['result' => $withdrawResult]);
                        }
                    }

                    if ($invoice->paid_amount > 0) {
                        $withdrawResult = $authUser->withdraw($invoice->paid_amount, $cashBoxId);
                        if ($withdrawResult !== true) {
                            Log::error('SaleInvoiceService: فشل سحب مبلغ مدفوع من خزنة البائع (إلغاء).', ['result' => $withdrawResult]);
                        }
                    }
                } else {
                    Log::warning('SaleInvoiceService: لم يتم العثور على العميل أثناء الإلغاء.', ['buyer_user_id' => $invoice->user_id]);
                }
            }

            $invoice->logCanceled('إلغاء فاتورة بيع رقم ' . $invoice->invoice_number);

            return $invoice;
        } catch (\Throwable $e) {
            Log::error('SaleInvoiceService: فشل في إلغاء فاتورة البيع.', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }
}

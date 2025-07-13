<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use App\Services\DocumentServiceInterface;
use App\Services\Traits\InvoiceHelperTrait;

class InstallmentSaleInvoiceService implements DocumentServiceInterface
{
    use InvoiceHelperTrait;

    public function create(array $data)
    {
        try {
            \Log::info('[InstallmentInvoice] 📥 البيانات المستلمة:', $data);

            $this->checkVariantsStock($data['items']);
            $invoice = $this->createInvoice($data);
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['created_by'] ?? null);
            $this->deductStockForItems($data['items']);

            if (!empty($data['paid_amount']) && $data['paid_amount'] > 0) {
                $user = \App\Models\User::findOrFail($data['user_id']);
                $withdrawResult = $user->withdraw($data['paid_amount'], $data['cash_box_id'] ?? null);
                if ($withdrawResult !== true) {
                    throw new \Exception('فشل خصم المبلغ من العميل: ' . json_encode($withdrawResult));
                }
                $staff = \App\Models\User::find($data['created_by'] ?? Auth::id());
                if ($staff) {
                    $depositResult = $staff->deposit($data['paid_amount'], $data['cash_box_id'] ?? null);
                    if ($depositResult !== true) {
                        throw new \Exception('فشل إيداع المبلغ في خزنة الموظف: ' . json_encode($depositResult));
                    }
                }
            }

            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            $invoice->logCreated('إنشاء فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);
            return $invoice;
        } catch (\Throwable $e) {
            \Log::error('[InstallmentInvoice] 💥 استثناء أثناء الإنشاء:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function update(array $data, Invoice $invoice)
    {
        try {
            \Log::info('[InstallmentInvoice] 🔄 تحديث الفاتورة رقم: ' . $invoice->id);

            $this->returnStockForItems($invoice);
            $this->deleteInvoiceItems($invoice);

            if ($invoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($invoice);
            }

            // ✅ تم حذف السحب من خزنة المستخدم أثناء التحديث

            $invoice->update([
                'invoice_type_id'   => $data['invoice_type_id'],
                'invoice_type_code' => $data['invoice_type_code'] ?? null,
                'due_date'          => $data['due_date'] ?? null,
                'status'            => $data['status'] ?? 'confirmed',
                'user_id'           => $data['user_id'],
                'gross_amount'      => $data['gross_amount'],
                'total_discount'    => $data['total_discount'] ?? 0,
                'net_amount'        => $data['net_amount'],
                'paid_amount'       => $data['paid_amount'] ?? 0,
                'remaining_amount'  => $data['remaining_amount'] ?? 0,
                'round_step'        => $data['round_step'] ?? null,
                'company_id'        => $data['company_id'] ?? null,
                'updated_by'        => $data['updated_by'] ?? null,
            ]);

            $this->checkVariantsStock($data['items']);
            $this->createInvoiceItems($invoice, $data['items'], $data['company_id'] ?? null, $data['updated_by'] ?? null);
            $this->deductStockForItems($data['items']);

            if (!is_null($data['paid_amount']) && floatval($data['paid_amount']) > 0) {
                $staff = \App\Models\User::find($data['created_by'] ?? Auth::id());
                if ($staff) {
                    $depositResult = $staff->deposit($data['paid_amount'], $data['cash_box_id'] ?? null);
                    if ($depositResult !== true) {
                        throw new \Exception('فشل إيداع المبلغ في خزنة الموظف: ' . json_encode($depositResult));
                    }
                }
            }

            if (isset($data['installment_plan'])) {
                app(InstallmentService::class)->createInstallments($data, $invoice->id);
            }

            $invoice->logUpdated('تحديث فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number);
            return $invoice;
        } catch (\Throwable $e) {
            \Log::error('[InstallmentInvoice] 💥 استثناء أثناء التحديث:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function cancel(Invoice $invoice): Invoice
    {
        try {
            \Log::info('[InstallmentInvoice] 🧹 بدء إلغاء الفاتورة رقم ' . $invoice->id);

            $this->returnStockForItems($invoice);
            $this->deleteInvoiceItems($invoice);

            if ($invoice->installmentPlan) {
                app(InstallmentService::class)->cancelInstallments($invoice);
            }

            \Log::info('[InstallmentInvoice] ✅ تم إلغاء الفاتورة بنجاح');
            return $invoice;
        } catch (\Throwable $e) {
            \Log::error('[InstallmentInvoice] ❌ فشل أثناء الإلغاء', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

<?php

namespace App\Services\Financial;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\FinancialTransaction;
use App\Models\Account;
use App\Models\CashBox;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\Financial\FinancialServiceInterface; // استيراد الواجهة

class FinancialService implements FinancialServiceInterface
{
    /**
     * يحسب الربح التقديري للفاتورة ويحدد حالتها والمبالغ المتبقية.
     * (خاص بفواتير المبيعات حيث يوجد ربح)
     *
     * @param array $invoiceData بيانات الفاتورة (يجب أن تحتوي على 'items' و 'net_amount' و 'paid_amount').
     * @return array بيانات الفاتورة المحدثة مع 'estimated_profit', 'status', 'remaining_amount'.
     */
    public function calculateInvoiceFinancials(array $invoiceData): array
    {
        Log::info('FinancialService: بدء حساب البيانات المالية للفاتورة.', ['invoiceData' => $invoiceData]);

        // حساب الربح التقديري (estimated_profit)
        $estimatedProfit = 0;
        foreach ($invoiceData['items'] as $item) {
            $itemCostPrice = $item['cost_price'] ?? 0;
            $itemUnitPrice = $item['unit_price'] ?? 0;
            $itemQuantity = $item['quantity'] ?? 0;
            $itemDiscount = $item['discount'] ?? 0;

            // حساب الربح لكل بند: (سعر البيع - الخصم لكل وحدة - سعر التكلفة) * الكمية
            $effectiveUnitPrice = $itemUnitPrice - ($itemDiscount / ($itemQuantity ?: 1)); // تجنب القسمة على صفر
            $estimatedProfit += ($effectiveUnitPrice - $itemCostPrice) * $itemQuantity;
        }
        $invoiceData['estimated_profit'] = $estimatedProfit;

        // تحديد حالة الفاتورة بناءً على paid_amount
        $netAmount = $invoiceData['net_amount'];
        $paidAmount = $invoiceData['paid_amount'] ?? 0;

        if ($paidAmount >= $netAmount) {
            $invoiceData['status'] = 'paid';
            $invoiceData['remaining_amount'] = 0;
        } elseif ($paidAmount > 0 && $paidAmount < $netAmount) {
            $invoiceData['status'] = 'partially_paid';
            $invoiceData['remaining_amount'] = $netAmount - $paidAmount;
        } else {
            $invoiceData['status'] = 'confirmed'; // أو 'unpaid' حسب تعريفك للحالة الافتراضية
            $invoiceData['remaining_amount'] = $netAmount;
        }

        Log::info('FinancialService: تم حساب البيانات المالية للفاتورة.', ['updatedInvoiceData' => $invoiceData]);
        return $invoiceData;
    }

    /**
     * ينشئ المعاملات المالية لفاتورة المبيعات (قيود الاستحقاق).
     *
     * @param Invoice $invoice الفاتورة التي تم إنشاؤها/تحديثها.
     * @param array $data البيانات الأصلية للفاتورة.
     * @param int $companyId معرف الشركة.
     * @param int|null $userId معرف المستخدم.
     * @param int $createdBy معرف المستخدم المنشئ/المحدث.
     * @return void
     * @throws \Throwable
     */
    public function createInvoiceFinancialTransactions(Invoice $invoice, array $data, int $companyId, int |null $userId, int $createdBy): void
    {
        Log::info('FinancialService: بدء إنشاء المعاملات المالية لفاتورة المبيعات.', ['invoice_id' => $invoice->id]);

        // جلب معرفات الحسابات (يجب أن تكون موجودة في جدول accounts)
        $accountsReceivableAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Accounts Receivable%')->firstOrFail()->id;
        $salesRevenueAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Sales Revenue%')->firstOrFail()->id;
        $costOfGoodsSoldAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Cost of Goods Sold%')->firstOrFail()->id;
        $inventoryAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Inventory%')->firstOrFail()->id;

        // القيد الأول: الاعتراف بالإيراد والمدينين (صافي المبلغ)
        FinancialTransaction::create([
            'transaction_type' => 'Invoice Sale',
            'debit_account_id' => $accountsReceivableAccountId, // المدين: حساب المدينين
            'credit_account_id' => $salesRevenueAccountId, // الدائن: حساب إيرادات المبيعات
            'amount' => $invoice->net_amount, // صافي المبلغ بعد الخصم
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'user_id' => $userId,
            'company_id' => $companyId,
            'transaction_date' => $invoice->created_at,
            'note' => 'فاتورة مبيعات رقم ' . $invoice->invoice_number,
            'created_by' => $createdBy,
        ]);

        // القيد الثاني: الاعتراف بتكلفة البضاعة المباعة والمخزون (مجموع cost_price للبنود)
        $totalCostOfGoodsSold = $invoice->items->sum(fn($item) => $item->cost_price * $item->quantity);
        if ($totalCostOfGoodsSold > 0) {
            FinancialTransaction::create([
                'transaction_type' => 'Cost of Goods Sold',
                'debit_account_id' => $costOfGoodsSoldAccountId, // المدين: حساب تكلفة البضاعة المباعة
                'credit_account_id' => $inventoryAccountId, // الدائن: حساب المخزون
                'amount' => $totalCostOfGoodsSold,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'user_id' => $userId,
                'company_id' => $companyId,
                'transaction_date' => $invoice->created_at,
                'note' => 'تكلفة بضاعة مباعة للفاتورة رقم ' . $invoice->invoice_number,
                'created_by' => $createdBy,
            ]);
        }
        Log::info('FinancialService: تم إنشاء المعاملات المالية لفاتورة المبيعات بنجاح.', ['invoice_id' => $invoice->id]);
    }

    /**
     * يحدث المعاملات المالية لفاتورة المبيعات (قيود الاستحقاق).
     *
     * @param Invoice $invoice الفاتورة التي تم تحديثها.
     * @param array $data البيانات الجديدة للفاتورة.
     * @param int $companyId معرف الشركة.
     * @param int|null $userId معرف المستخدم.
     * @param int $updatedBy معرف المستخدم المحدث.
     * @return void
     * @throws \Throwable
     */
    public function updateInvoiceFinancialTransactions(Invoice $invoice, array $data, int $companyId, int |null $userId, int $updatedBy): void
    {
        Log::info('FinancialService: بدء تحديث المعاملات المالية لفاتورة المبيعات.', ['invoice_id' => $invoice->id]);

        $accountsReceivableAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Accounts Receivable%')->firstOrFail()->id;
        $salesRevenueAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Sales Revenue%')->firstOrFail()->id;
        $costOfGoodsSoldAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Cost of Goods Sold%')->firstOrFail()->id;
        $inventoryAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Inventory%')->firstOrFail()->id;

        // تحديث قيد الإيراد والمدينين
        $revenueTransaction = FinancialTransaction::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('transaction_type', 'Invoice Sale')
            ->first();
        if ($revenueTransaction) {
            $revenueTransaction->update([
                'amount' => $invoice->net_amount,
                'note' => 'فاتورة مبيعات رقم ' . $invoice->invoice_number . ' (محدثة)',
                'updated_by' => $updatedBy,
            ]);
        } else {
            // في حال لم يكن موجوداً وتمت إضافة بنود للفاتورة
            FinancialTransaction::create([
                'transaction_type' => 'Invoice Sale',
                'debit_account_id' => $accountsReceivableAccountId,
                'credit_account_id' => $salesRevenueAccountId,
                'amount' => $invoice->net_amount,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'user_id' => $userId,
                'company_id' => $companyId,
                'transaction_date' => $invoice->updated_at,
                'note' => 'فاتورة مبيعات رقم ' . $invoice->invoice_number . ' (تم إنشاؤها بعد التحديث)',
                'created_by' => $updatedBy,
            ]);
        }

        // تحديث قيد تكلفة البضاعة المباعة والمخزون
        $cogsTransaction = FinancialTransaction::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('transaction_type', 'Cost of Goods Sold')
            ->first();
        $totalCostOfGoodsSold = $invoice->items->sum(fn($item) => $item->cost_price * $item->quantity);

        if ($cogsTransaction) {
            $cogsTransaction->update([
                'amount' => $totalCostOfGoodsSold,
                'note' => 'تكلفة بضاعة مباعة للفاتورة رقم ' . $invoice->invoice_number . ' (محدثة)',
                'updated_by' => $updatedBy,
            ]);
        } elseif ($totalCostOfGoodsSold > 0) {
            // إذا لم يكن هناك قيد COGS سابق وتم إضافة بنود بتكلفة
            FinancialTransaction::create([
                'transaction_type' => 'Cost of Goods Sold',
                'debit_account_id' => $costOfGoodsSoldAccountId,
                'credit_account_id' => $inventoryAccountId,
                'amount' => $totalCostOfGoodsSold,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'user_id' => $userId,
                'company_id' => $companyId,
                'transaction_date' => $invoice->updated_at,
                'note' => 'تكلفة بضاعة مباعة للفاتورة رقم ' . $invoice->invoice_number . ' (تم إنشاؤها بعد التحديث)',
                'created_by' => $updatedBy,
            ]);
        }
        Log::info('FinancialService: تم تحديث المعاملات المالية لفاتورة المبيعات بنجاح.', ['invoice_id' => $invoice->id]);
    }

    /**
     * يعكس/يحذف المعاملات المالية المرتبطة بأي فاتورة (سواء بيع أو شراء).
     *
     * @param Invoice $invoice الفاتورة المراد عكس معاملاتها.
     * @return void
     * @throws \Throwable
     */
    public function reverseInvoiceFinancialTransactions(Invoice $invoice): void
    {
        Log::info('FinancialService: بدء عكس المعاملات المالية للفاتورة.', ['invoice_id' => $invoice->id]);
        // يجب تحميل المعاملات المالية قبل محاولة حذفها
        $invoice->load('financialTransactions');
        foreach ($invoice->financialTransactions as $transaction) {
            $transaction->delete();
        }
        Log::info('FinancialService: تم عكس المعاملات المالية للفاتورة بنجاح.', ['invoice_id' => $invoice->id]);
    }

    /**
     * يعالج الدفعة الأولية أو الإضافية لفاتورة المبيعات وينشئ المعاملات المالية والدفعات.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بالدفعة.
     * @param float $amount مبلغ الدفعة.
     * @param int $cashBoxId معرف الصندوق النقدي.
     * @param int $companyId معرف الشركة.
     * @param int|null $userId معرف المستخدم (العميل).
     * @param int $createdBy معرف المستخدم المنشئ.
     * @return Payment الدفعة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function handleInvoicePayment(Invoice $invoice, float $amount, int $cashBoxId, int $companyId, int |null $userId, int $createdBy): Payment
    {
        Log::info('FinancialService: بدء معالجة دفعة فاتورة المبيعات.', ['invoice_id' => $invoice->id, 'amount' => $amount]);

        $cashBox = CashBox::findOrFail($cashBoxId);
        $accountsReceivableAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Accounts Receivable%')->firstOrFail()->id;
        $cashBoxAccount = $cashBox->account_id ?? Account::where('company_id', $companyId)->where('name', 'like', '%Cash%')->firstOrFail()->id;

        $payment = Payment::create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'created_by' => $createdBy,
            'payment_date' => now(),
            'amount' => $amount,
            'method' => $cashBox->paymentMethod->name ?? 'Unknown', // استخدام اسم طريقة الدفع من الصندوق النقدي
            'notes' => 'دفعة لفاتورة مبيعات رقم ' . $invoice->invoice_number,
            'is_split' => false, // هذه دفعة واحدة
            'payment_type' => 'inflow', // دفعة واردة
            'cash_box_id' => $cashBoxId,
            'payable_type' => Invoice::class,
            'payable_id' => $invoice->id,
            // حساب الربح الفعلي المحقق من هذه الدفعة
            'realized_profit_amount' => ($invoice->net_amount > 0) ? ($amount / $invoice->net_amount) * $invoice->estimated_profit : 0,
        ]);

        $paymentTransaction = FinancialTransaction::create([
            'transaction_type' => 'Payment Received',
            'debit_account_id' => $cashBoxAccount, // المدين: حساب الصندوق النقدي
            'credit_account_id' => $accountsReceivableAccountId, // الدائن: حساب المدينين (لأنهم دفعوا جزءًا من دينهم)
            'amount' => $amount,
            'source_type' => Payment::class, // مصدر المعاملة هو الدفعة نفسها
            'source_id' => $payment->id,
            'user_id' => $userId,
            'company_id' => $companyId,
            'cash_box_id' => $cashBoxId,
            'transaction_date' => $payment->payment_date,
            'note' => 'استلام دفعة نقدية لفاتورة مبيعات رقم ' . $invoice->invoice_number,
            'created_by' => $createdBy,
        ]);

        // ربط الدفعة بالمعاملة المالية
        $payment->financial_transaction_id = $paymentTransaction->id;
        $payment->save();

        Log::info('FinancialService: تم معالجة دفعة فاتورة المبيعات بنجاح.', ['payment_id' => $payment->id]);
        return $payment;
    }

    /**
     * يعكس/يحذف الدفعات والمعاملات المالية المرتبطة بها.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بالدفعات.
     * @return void
     * @throws \Throwable
     */
    public function reverseInvoicePayments(Invoice $invoice): void
    {
        Log::info('FinancialService: بدء عكس دفعات الفاتورة.', ['invoice_id' => $invoice->id]);
        // يجب تحميل الدفعات ومعاملاتها المالية قبل محاولة حذفها
        $invoice->load('payments.financialTransaction');
        foreach ($invoice->payments as $payment) {
            // إذا كانت الدفعة مرتبطة بتفاصيل أقساط، قد تحتاج إلى منطق خاص هنا
            // لمنع الحذف أو عكس تأثيرها على الأقساط قبل حذف الدفعة
            if ($payment->installmentPaymentDetails()->exists()) {
                // يمكن رمي استثناء أو تسجيل خطأ هنا إذا كان لا يمكن حذف الدفعة
                Log::warning('FinancialService: لا يمكن حذف الدفعة ' . $payment->id . ' لأنها مرتبطة بأقساط. يرجى التعامل مع الأقساط أولاً.', ['payment_id' => $payment->id]);
                // يمكنك اختيار رمي استثناء هنا:
                // throw new \Exception('لا يمكن حذف الدفعة المرتبطة بأقساط مباشرة.');
                continue; // تخطي هذه الدفعة إذا كانت مرتبطة بأقساط
            }

            if ($payment->financialTransaction) {
                $payment->financialTransaction->delete();
            }
            $payment->delete();
        }
        Log::info('FinancialService: تم عكس دفعات الفاتورة بنجاح.', ['invoice_id' => $invoice->id]);
    }

    // --------------------------------------------------------------------
    // طرق جديدة خاصة بفواتير الشراء
    // --------------------------------------------------------------------

    /**
     * ينشئ المعاملات المالية لفاتورة الشراء (قيود الاستحقاق).
     *
     * @param Invoice $invoice الفاتورة التي تم إنشاؤها/تحديثها.
     * @param array $data البيانات الأصلية للفاتورة.
     * @param int $companyId معرف الشركة.
     * @param int|null $userId معرف المستخدم (المورد).
     * @param int $createdBy معرف المستخدم المنشئ/المحدث.
     * @return void
     * @throws \Throwable
     */
    public function createPurchaseFinancialTransactions(Invoice $invoice, array $data, int $companyId, int |null $userId, int $createdBy): void
    {
        Log::info('FinancialService: بدء إنشاء المعاملات المالية لفاتورة الشراء.', ['invoice_id' => $invoice->id]);

        // جلب معرفات الحسابات
        $accountsPayableAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Accounts Payable%')->firstOrFail()->id;
        $inventoryAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Inventory%')->firstOrFail()->id;
        // إذا كان هناك حساب للمشتريات (Purchases) بدلاً من المخزون مباشرة، استخدمه.
        // مثال: $purchasesAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Purchases%')->firstOrFail()->id;

        // القيد الأول: الاعتراف بالمخزون/المشتريات والذمم الدائنة (صافي المبلغ)
        FinancialTransaction::create([
            'transaction_type' => 'Purchase Invoice',
            'debit_account_id' => $inventoryAccountId, // المدين: حساب المخزون (أو المشتريات)
            'credit_account_id' => $accountsPayableAccountId, // الدائن: حساب الذمم الدائنة
            'amount' => $invoice->net_amount, // صافي المبلغ بعد الخصم
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'user_id' => $userId, // المورد
            'company_id' => $companyId,
            'transaction_date' => $invoice->created_at,
            'note' => 'فاتورة شراء رقم ' . $invoice->invoice_number,
            'created_by' => $createdBy,
        ]);

        Log::info('FinancialService: تم إنشاء المعاملات المالية لفاتورة الشراء بنجاح.', ['invoice_id' => $invoice->id]);
    }

    /**
     * يحدث المعاملات المالية لفاتورة الشراء (قيود الاستحقاق).
     *
     * @param Invoice $invoice الفاتورة التي تم تحديثها.
     * @param array $data البيانات الجديدة للفاتورة.
     * @param int $companyId معرف الشركة.
     * @param int|null $userId معرف المستخدم (المورد).
     * @param int $updatedBy معرف المستخدم المحدث.
     * @return void
     * @throws \Throwable
     */
    public function updatePurchaseFinancialTransactions(Invoice $invoice, array $data, int $companyId, int |null $userId, int $updatedBy): void
    {
        Log::info('FinancialService: بدء تحديث المعاملات المالية لفاتورة الشراء.', ['invoice_id' => $invoice->id]);

        $accountsPayableAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Accounts Payable%')->firstOrFail()->id;
        $inventoryAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Inventory%')->firstOrFail()->id;

        // تحديث قيد فاتورة الشراء
        $purchaseTransaction = FinancialTransaction::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('transaction_type', 'Purchase Invoice')
            ->first();

        if ($purchaseTransaction) {
            $purchaseTransaction->update([
                'amount' => $invoice->net_amount,
                'note' => 'فاتورة شراء رقم ' . $invoice->invoice_number . ' (محدثة)',
                'updated_by' => $updatedBy,
            ]);
        } else {
            // في حال لم يكن موجوداً (سيناريو غير متوقع ولكن كـ fallback)
            FinancialTransaction::create([
                'transaction_type' => 'Purchase Invoice',
                'debit_account_id' => $inventoryAccountId,
                'credit_account_id' => $accountsPayableAccountId,
                'amount' => $invoice->net_amount,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'user_id' => $userId,
                'company_id' => $companyId,
                'transaction_date' => $invoice->updated_at,
                'note' => 'فاتورة شراء رقم ' . $invoice->invoice_number . ' (تم إنشاؤها بعد التحديث)',
                'created_by' => $updatedBy,
            ]);
        }
        Log::info('FinancialService: تم تحديث المعاملات المالية لفاتورة الشراء بنجاح.', ['invoice_id' => $invoice->id]);
    }

    /**
     * يعالج الدفعة الأولية أو الإضافية لفاتورة الشراء (دفعة صادرة) وينشئ المعاملات المالية والدفعات.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بالدفعة.
     * @param float $amount مبلغ الدفعة.
     * @param int $cashBoxId معرف الصندوق النقدي.
     * @param int $companyId معرف الشركة.
     * @param int|null $userId معرف المستخدم (المورد).
     * @param int $createdBy معرف المستخدم المنشئ.
     * @return Payment الدفعة التي تم إنشاؤها.
     * @throws \Throwable
     */
    public function handlePurchasePayment(Invoice $invoice, float $amount, int $cashBoxId, int $companyId, int |null $userId, int $createdBy): Payment
    {
        Log::info('FinancialService: بدء معالجة دفعة فاتورة الشراء.', ['invoice_id' => $invoice->id, 'amount' => $amount]);

        $cashBox = CashBox::findOrFail($cashBoxId);
        $accountsPayableAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Accounts Payable%')->firstOrFail()->id;
        $cashBoxAccount = $cashBox->account_id ?? Account::where('company_id', $companyId)->where('name', 'like', '%Cash%')->firstOrFail()->id;

        $payment = Payment::create([
            'user_id' => $userId, // المورد
            'company_id' => $companyId,
            'created_by' => $createdBy,
            'payment_date' => now(),
            'amount' => $amount,
            'method' => $cashBox->paymentMethod->name ?? 'Unknown',
            'notes' => 'دفعة لفاتورة شراء رقم ' . $invoice->invoice_number,
            'is_split' => false,
            'payment_type' => 'outflow', // دفعة صادرة
            'cash_box_id' => $cashBoxId,
            'payable_type' => Invoice::class,
            'payable_id' => $invoice->id,
            'realized_profit_amount' => 0, // لا يوجد ربح محقق من دفع فاتورة الشراء
        ]);

        $paymentTransaction = FinancialTransaction::create([
            'transaction_type' => 'Payment Made',
            'debit_account_id' => $accountsPayableAccountId, // المدين: حساب الذمم الدائنة (لأننا سددنا جزءًا من ديننا)
            'credit_account_id' => $cashBoxAccount, // الدائن: حساب الصندوق النقدي (الذي دفع المبلغ)
            'amount' => $amount,
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'user_id' => $userId, // المورد
            'company_id' => $companyId,
            'cash_box_id' => $cashBoxId,
            'transaction_date' => $payment->payment_date,
            'note' => 'سداد دفعة نقدية لفاتورة شراء رقم ' . $invoice->invoice_number,
            'created_by' => $createdBy,
        ]);

        $payment->financial_transaction_id = $paymentTransaction->id;
        $payment->save();

        Log::info('FinancialService: تم معالجة دفعة فاتورة الشراء بنجاح.', ['payment_id' => $payment->id]);
        return $payment;
    }

    /**
     * يعكس/يحذف المعاملات المالية المرتبطة بفاتورة الشراء.
     *
     * @param Invoice $invoice الفاتورة المراد عكس معاملاتها.
     * @return void
     * @throws \Throwable
     */
    public function reversePurchaseFinancialTransactions(Invoice $invoice): void
    {
        Log::info('FinancialService: بدء عكس المعاملات المالية لفاتورة الشراء.', ['invoice_id' => $invoice->id]);
        // يجب تحميل المعاملات المالية المرتبطة بهذا النوع من الفواتير
        $transactionsToReverse = FinancialTransaction::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('transaction_type', 'Purchase Invoice') // نوع المعاملة الخاص بفواتير الشراء
            ->get();

        foreach ($transactionsToReverse as $transaction) {
            $transaction->delete();
        }
        Log::info('FinancialService: تم عكس المعاملات المالية لفاتورة الشراء بنجاح.', ['invoice_id' => $invoice->id]);
    }

    /**
     * يعكس/يحذف الدفعات والمعاملات المالية المرتبطة بفاتورة الشراء.
     *
     * @param Invoice $invoice الفاتورة المرتبطة بالدفعات.
     * @return void
     * @throws \Throwable
     */
    public function reversePurchasePayments(Invoice $invoice): void
    {
        Log::info('FinancialService: بدء عكس دفعات فاتورة الشراء.', ['invoice_id' => $invoice->id]);
        // يجب تحميل الدفعات ومعاملاتها المالية قبل محاولة حذفها
        $invoice->load('payments.financialTransaction');
        foreach ($invoice->payments as $payment) {
            // تأكد من أن الدفعة هي دفعة شراء (outflow) قبل عكسها
            if ($payment->payment_type === 'outflow') {
                if ($payment->financialTransaction) {
                    $payment->financialTransaction->delete();
                }
                $payment->delete();
            } else {
                Log::warning('FinancialService: تم تخطي دفعة غير صادرة (outflow) أثناء عكس دفعات فاتورة الشراء.', ['payment_id' => $payment->id, 'payment_type' => $payment->payment_type]);
            }
        }
        Log::info('FinancialService: تم عكس دفعات فاتورة الشراء بنجاح.', ['invoice_id' => $invoice->id]);
    }

    // --------------------------------------------------------------------
    // طرق جديدة خاصة بفواتير البيع بالتقسيط
    // --------------------------------------------------------------------

    /**
     * ينشئ المعاملات المالية لفاتورة البيع بالتقسيط (قيود الاستحقاق).
     *
     * @param Invoice $invoice الفاتورة التي تم إنشاؤها/تحديثها.
     * @param array $data البيانات الأصلية للفاتورة.
     * @param int $companyId معرف الشركة.
     * @param int|null $userId معرف المستخدم (العميل).
     * @param int $createdBy معرف المستخدم المنشئ/المحدث.
     * @return void
     * @throws \Throwable
     */
    public function createInstallmentSaleFinancialTransactions(Invoice $invoice, array $data, int $companyId, int |null $userId, int $createdBy): void
    {
        Log::info('FinancialService: بدء إنشاء المعاملات المالية لفاتورة البيع بالتقسيط.', ['invoice_id' => $invoice->id]);

        // جلب معرفات الحسابات
        $accountsReceivableAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Accounts Receivable%')->firstOrFail()->id;
        $salesRevenueAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Sales Revenue%')->firstOrFail()->id;
        $costOfGoodsSoldAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Cost of Goods Sold%')->firstOrFail()->id;
        $inventoryAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Inventory%')->firstOrFail()->id;
        $unearnedRevenueAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Unearned Revenue%')->firstOrFail()->id; // إيرادات غير مكتسبة

        // القيد الأول: الاعتراف بالمدينين وإيرادات غير مكتسبة (إجمالي مبلغ الفاتورة)
        // هذا يسجل المبلغ المستحق بالكامل كإيراد غير مكتسب حتى يتم سداد الأقساط
        FinancialTransaction::create([
            'transaction_type' => 'Installment Sale - Accrual',
            'debit_account_id' => $accountsReceivableAccountId, // المدين: حساب المدينين
            'credit_account_id' => $unearnedRevenueAccountId, // الدائن: حساب إيرادات غير مكتسبة
            'amount' => $invoice->net_amount, // صافي المبلغ الكلي للفاتورة
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'user_id' => $userId,
            'company_id' => $companyId,
            'transaction_date' => $invoice->created_at,
            'note' => 'فاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number . ' (تسجيل استحقاق)',
            'created_by' => $createdBy,
        ]);

        // القيد الثاني: الاعتراف بتكلفة البضاعة المباعة والمخزون (مجموع cost_price للبنود)
        $totalCostOfGoodsSold = $invoice->items->sum(fn($item) => $item->cost_price * $item->quantity);
        if ($totalCostOfGoodsSold > 0) {
            FinancialTransaction::create([
                'transaction_type' => 'Installment Sale - COGS',
                'debit_account_id' => $costOfGoodsSoldAccountId, // المدين: حساب تكلفة البضاعة المباعة
                'credit_account_id' => $inventoryAccountId, // الدائن: حساب المخزون
                'amount' => $totalCostOfGoodsSold,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'user_id' => $userId,
                'company_id' => $companyId,
                'transaction_date' => $invoice->created_at,
                'note' => 'تكلفة بضاعة مباعة لفاتورة بيع بالتقسيط رقم ' . $invoice->invoice_number,
                'created_by' => $createdBy,
            ]);
        }
        Log::info('FinancialService: تم إنشاء المعاملات المالية لفاتورة البيع بالتقسيط بنجاح.', ['invoice_id' => $invoice->id]);
    }

    /**
     * يعكس/يحذف المعاملات المالية المرتبطة بفاتورة البيع بالتقسيط.
     *
     * @param Invoice $invoice الفاتورة المراد عكس معاملاتها.
     * @return void
     * @throws \Throwable
     */
    public function reverseInstallmentSaleFinancialTransactions(Invoice $invoice): void
    {
        Log::info('FinancialService: بدء عكس المعاملات المالية لفاتورة البيع بالتقسيط.', ['invoice_id' => $invoice->id]);
        // يجب تحميل المعاملات المالية المرتبطة بهذا النوع من الفواتير
        $transactionsToReverse = FinancialTransaction::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->whereIn('transaction_type', ['Installment Sale - Accrual', 'Installment Sale - COGS'])
            ->get();

        foreach ($transactionsToReverse as $transaction) {
            $transaction->delete();
        }
        Log::info('FinancialService: تم عكس المعاملات المالية لفاتورة البيع بالتقسيط بنجاح.', ['invoice_id' => $invoice->id]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Payment;
use App\Models\FinancialTransaction; // إضافة نموذج المعاملات المالية
use App\Models\Account; // إضافة نموذج الحسابات
use App\Models\CashBox; // لإحضار معلومات الصندوق النقدي
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'user',
            // 'installments', // تم حذف هذا الربط المباشر، الآن يتم عبر installmentPaymentDetails
            'cashBox',
            // 'paymentMethod', // تم استبداله بـ 'method' كحقل نصي، ليس علاقة مباشرة
            'creator',
            'company',
            'financialTransaction', // إضافة العلاقة مع المعاملة المالية
            'payable', // إضافة العلاقة Polymorphic (للفاتورة، القسط، إلخ)
        ];
    }

    /**
     * عرض قائمة المدفوعات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = Payment::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع المدفوعات
            } elseif ($authUser->hasAnyPermission([perm_key('payments.view_all'), perm_key('admin.company')])) {
                // يرى جميع المدفوعات الخاصة بالشركة النشطة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.view_children'))) {
                // يرى المدفوعات التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.view_self'))) {
                // يرى المدفوعات التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض المدفوعات.');
            }

            // فلاتر الطلب الإضافية
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            // تم استبدال payment_method_id بـ method
            if ($request->filled('method')) {
                $query->where('method', $request->input('method'));
            }
            if ($request->filled('cash_box_id')) {
                $query->where('cash_box_id', $request->input('cash_box_id'));
            }
            if ($request->filled('amount_from')) {
                $query->where('amount', '>=', $request->input('amount_from'));
            }
            if ($request->filled('amount_to')) {
                $query->where('amount', '<=', $request->input('amount_to'));
            }
            // تم تغيير paid_at إلى payment_date
            if ($request->filled('payment_date_from')) {
                $query->where('payment_date', '>=', $request->input('payment_date_from') . ' 00:00:00');
            }
            if ($request->filled('payment_date_to')) {
                $query->where('payment_date', '<=', $request->input('payment_date_to') . ' 23:59:59');
            }
            // إضافة فلتر لـ payment_type
            if ($request->filled('payment_type')) {
                $query->where('payment_type', $request->input('payment_type'));
            }
            // إضافة فلتر لـ payable_type (مثلاً: App\Models\Invoice)
            if ($request->filled('payable_type')) {
                $query->where('payable_type', $request->input('payable_type'));
            }
            // إضافة فلتر لـ payable_id
            if ($request->filled('payable_id')) {
                $query->where('payable_id', $request->input('payable_id'));
            }


            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, (int) $request->input('per_page', 20));
            $sortField = $request->input('sort_by', 'payment_date'); // تم تغيير paid_at إلى payment_date
            $sortOrder = $request->input('sort_order', 'desc');

            $payments = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($payments->isEmpty()) {
                return api_success([], 'لم يتم العثور على مدفوعات.');
            } else {
                return api_success(PaymentResource::collection($payments), 'تم جلب المدفوعات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين دفعة جديدة.
     *
     * @param StorePaymentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('payments.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء مدفوعات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;
                $validatedData['company_id'] = $companyId;

                // التحقق من أن صندوق النقد ينتمي لنفس الشركة
                // (تم حذف التحقق من payment_method_id لأنه أصبح حقل نصي)
                $cashBox = CashBox::where('id', $validatedData['cash_box_id'])
                    ->where('company_id', $companyId)
                    ->firstOrFail();

                // إنشاء الدفعة
                $payment = Payment::create($validatedData);

                // --------------------------------------------------------------------
                // جزء المحاسبة: إنشاء قيد مالي في FinancialTransactions
                // --------------------------------------------------------------------
                $debitAccountId = null;
                $creditAccountId = null;

                // تحديد حسابات المدين والدائن بناءً على نوع الدفعة
                if ($validatedData['payment_type'] === 'inflow') { // دفعة واردة (إيراد)
                    // المدين: حساب الصندوق النقدي (الذي استقبل المبلغ)
                    // يجب أن يكون لكل صندوق نقدي حساب محاسبي مقابل
                    $debitAccountId = $cashBox->account_id ?? Account::where('company_id', $companyId)->where('name', 'like', '%Cash%')->first()->id; // مثال: حساب النقدية

                    // الدائن: حساب الإيرادات (أو حساب وسيط إذا كان هناك كيان Payable)
                    // يمكنك جلب هذا الحساب بناءً على نوع payable_type أو افتراضيًا
                    if ($validatedData['payable_type'] === 'App\\Models\\Invoice') {
                        // مثال: إذا كانت فاتورة، قد يكون حساب إيرادات المبيعات
                        $creditAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Sales Revenue%')->first()->id;
                    } else {
                        // حساب إيرادات عام
                        $creditAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%General Revenue%')->first()->id;
                    }
                } elseif ($validatedData['payment_type'] === 'outflow') { // دفعة صادرة (مصروف)
                    // المدين: حساب المصروفات (أو حساب وسيط إذا كان هناك كيان Payable)
                    if ($validatedData['payable_type'] === 'App\\Models\\Purchase') {
                        // مثال: إذا كانت دفعة شراء، قد يكون حساب المشتريات
                        $debitAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Purchases%')->first()->id;
                    } else {
                        // حساب مصروفات عام
                        $debitAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%General Expense%')->first()->id;
                    }

                    // الدائن: حساب الصندوق النقدي (الذي دفع المبلغ)
                    $creditAccountId = $cashBox->account_id ?? Account::where('company_id', $companyId)->where('name', 'like', '%Cash%')->first()->id; // مثال: حساب النقدية
                }

                if (!$debitAccountId || !$creditAccountId) {
                    throw new \Exception('لم يتم العثور على حسابات المدين أو الدائن للمعاملة المالية.');
                }

                $financialTransaction = FinancialTransaction::create([
                    'transaction_type' => $validatedData['payment_type'] === 'inflow' ? 'Payment Received' : 'Payment Made',
                    'debit_account_id' => $debitAccountId,
                    'credit_account_id' => $creditAccountId,
                    'amount' => $validatedData['amount'],
                    'source_type' => Payment::class, // مصدر المعاملة هو الدفعة نفسها
                    'source_id' => $payment->id,
                    'user_id' => $validatedData['user_id'] ?? null,
                    'company_id' => $companyId,
                    'cash_box_id' => $validatedData['cash_box_id'],
                    'transaction_date' => $validatedData['payment_date'],
                    'note' => $validatedData['notes'] ?? 'دفعة مالية',
                    'created_by' => $authUser->id,
                ]);

                // ربط الدفعة بالمعاملة المالية
                $payment->financial_transaction_id = $financialTransaction->id;
                $payment->save();
                // --------------------------------------------------------------------

                $payment->load($this->relations);
                DB::commit();
                return api_success(new PaymentResource($payment), 'تم إنشاء الدفعة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين الدفعة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ الدفعة: ' . $e->getMessage(), [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض دفعة محددة.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $payment = Payment::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payments.view_all'), perm_key('admin.company')])) {
                $canView = $payment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.view_children'))) {
                $canView = $payment->belongsToCurrentCompany() && $payment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.view_self'))) {
                $canView = $payment->belongsToCurrentCompany() && $payment->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new PaymentResource($payment), 'تم استرداد الدفعة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه الدفعة.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث دفعة محددة.
     *
     * @param UpdatePaymentRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdatePaymentRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $payment = Payment::with(['company', 'creator', 'financialTransaction'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payments.update_all'), perm_key('admin.company')])) {
                $canUpdate = $payment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.update_children'))) {
                $canUpdate = $payment->belongsToCurrentCompany() && $payment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.update_self'))) {
                $canUpdate = $payment->belongsToCurrentCompany() && $payment->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذه الدفعة.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                // التحقق من أن صندوق النقد ينتمي لنفس الشركة إذا تم تغييرها
                if (isset($validatedData['cash_box_id']) && $validatedData['cash_box_id'] != $payment->cash_box_id) {
                    CashBox::where('id', $validatedData['cash_box_id'])
                        ->where('company_id', $companyId)
                        ->firstOrFail();
                }
                // تم حذف التحقق من payment_method_id لأنه أصبح حقل نصي

                $payment->update($validatedData);

                // --------------------------------------------------------------------
                // جزء المحاسبة: تحديث القيد المالي في FinancialTransactions
                // --------------------------------------------------------------------
                if ($payment->financialTransaction) {
                    $financialTransaction = $payment->financialTransaction;

                    $debitAccountId = null;
                    $creditAccountId = null;

                    // إعادة تحديد حسابات المدين والدائن بناءً على نوع الدفعة المحدث
                    $cashBox = CashBox::find($payment->cash_box_id); // جلب الصندوق النقدي المحدث

                    if ($payment->payment_type === 'inflow') {
                        $debitAccountId = $cashBox->account_id ?? Account::where('company_id', $companyId)->where('name', 'like', '%Cash%')->first()->id;
                        if ($payment->payable_type === 'App\\Models\\Invoice') {
                            $creditAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Sales Revenue%')->first()->id;
                        } else {
                            $creditAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%General Revenue%')->first()->id;
                        }
                    } elseif ($payment->payment_type === 'outflow') {
                        if ($payment->payable_type === 'App\\Models\\Purchase') {
                            $debitAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%Purchases%')->first()->id;
                        } else {
                            $debitAccountId = Account::where('company_id', $companyId)->where('name', 'like', '%General Expense%')->first()->id;
                        }
                        $creditAccountId = $cashBox->account_id ?? Account::where('company_id', $companyId)->where('name', 'like', '%Cash%')->first()->id;
                    }

                    if (!$debitAccountId || !$creditAccountId) {
                        throw new \Exception('لم يتم العثور على حسابات المدين أو الدائن لتحديث المعاملة المالية.');
                    }

                    $financialTransaction->update([
                        'transaction_type' => $payment->payment_type === 'inflow' ? 'Payment Received' : 'Payment Made',
                        'debit_account_id' => $debitAccountId,
                        'credit_account_id' => $creditAccountId,
                        'amount' => $payment->amount,
                        'cash_box_id' => $payment->cash_box_id,
                        'transaction_date' => $payment->payment_date,
                        'note' => $payment->notes ?? 'دفعة مالية محدثة',
                        // source_type و source_id لا تتغير هنا لأنها تشير إلى الدفعة نفسها
                    ]);
                }
                // --------------------------------------------------------------------

                $payment->load($this->relations);
                DB::commit();
                return api_success(new PaymentResource($payment), 'تم تحديث الدفعة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث الدفعة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الدفعة: ' . $e->getMessage(), [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف دفعة محددة.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $payment = Payment::with(['company', 'creator', 'installmentPaymentDetails', 'financialTransaction'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payments.delete_all'), perm_key('admin.company')])) {
                $canDelete = $payment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.delete_children'))) {
                $canDelete = $payment->belongsToCurrentCompany() && $payment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payments.delete_self'))) {
                $canDelete = $payment->belongsToCurrentCompany() && $payment->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذه الدفعة.');
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كانت الدفعة مرتبطة بأي تفاصيل دفعات أقساط
                // هذا يمنع حذف دفعة إذا كانت قد غطت أقساطًا بالفعل
                if ($payment->installmentPaymentDetails()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف الدفعة. إنها مرتبطة بتفاصيل دفعات أقساط موجودة.', [], 409);
                }

                // --------------------------------------------------------------------
                // جزء المحاسبة: حذف القيد المالي المرتبط
                // --------------------------------------------------------------------
                if ($payment->financialTransaction) {
                    $payment->financialTransaction->delete();
                }
                // --------------------------------------------------------------------

                $deletedPayment = $payment->replicate();
                $deletedPayment->setRelations($payment->getRelations());

                $payment->delete();
                DB::commit();
                return api_success(new PaymentResource($deletedPayment), 'تم حذف الدفعة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

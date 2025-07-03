<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CashBox;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Support\Facades\Auth;
use Throwable;

// تأكد من أن هذه الدالة متاحة عالميًا أو داخل هذا النطاق
if (!function_exists('perm_key')) {
    function perm_key(string $permission): string
    {
        return $permission;
    }
}

class TransactionController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'user',
            'targetUser',
            'cashbox',
            'targetCashbox',
            'company',
            'creator',
        ];
    }

    /**
     * تحويل الرصيد بين المستخدمين والصناديق.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transfer(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحية خاصة لتحويل الأموال (موازية لـ transactions.create)
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('transactions.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لتحويل الأموال.');
            }

            $validated = $request->validate([
                'target_user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'from_cash_box_id' => ['required', 'exists:cash_boxes,id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    $cashBox = CashBox::with(['company'])->find($value);
                    if (!$cashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)) {
                        $fail('صندوق النقد المصدر المحدد غير صالح أو غير متاح.');
                    }
                }],
                'to_cash_box_id' => ['required', 'exists:cash_boxes,id', 'different:from_cash_box_id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    $toCashBox = CashBox::with(['company'])->find($value);
                    if (!$toCashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $toCashBox->company_id !== $companyId)) {
                        $fail('صندوق النقد المستهدف المحدد غير صالح أو غير متاح.');
                    }
                }],
                'description' => 'nullable|string',
            ]);

            $targetUser = User::where('id', $validated['target_user_id'])->first();

            if (!$targetUser) {
                return api_not_found('المستخدم المستهدف غير موجود.');
            }

            $fromCashBox = CashBox::findOrFail($validated['from_cash_box_id']);
            $toCashBox = CashBox::findOrFail($validated['to_cash_box_id']);

            // التأكد من أن الصناديق تابعة لشركة المستخدم (أو أن المستخدم super_admin)
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                if (!$fromCashBox->belongsToCurrentCompany() || !$toCashBox->belongsToCurrentCompany()) {
                    return api_forbidden('يمكنك فقط تحويل الأموال بين الصناديق النقدية داخل شركتك.');
                }
            }

            DB::beginTransaction();
            try {
                // تنفيذ التحويل
                $authUser->transferTo(
                    $targetUser,
                    $validated['amount'],
                    $validated['from_cash_box_id'],
                    $validated['to_cash_box_id'],
                    $validated['description'] ?? null
                );

                DB::commit();
                return api_success([], 'تم التحويل بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('فشل التحويل. يرجى المحاولة مرة أخرى.', [], 500);
            }
        } catch (ValidationException $e) {
            return api_error('فشل التحقق من صحة البيانات أثناء تحويل الأموال.', $e->errors(), 422);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * إرجاع جميع عمليات المستخدم.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userTransactions(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $query = Transaction::with($this->relations);

            // تطبيق صلاحية 'transactions.view_self'
            // المستخدم يمكنه رؤية معاملاته الخاصة فقط.
            $query->where('user_id', $authUser->id)
                ->where('company_id', $companyId); // تأكد من أنها ضمن شركته

            // فلاتر البحث
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('cashbox_id')) {
                $query->where('cashbox_id', $request->input('cashbox_id'));
            }
            if ($request->filled('target_user_id')) {
                $query->where('target_user_id', $request->input('target_user_id'));
            }
            $createdAtFrom = $request->input('created_at_from');
            if (!empty($createdAtFrom)) {
                $query->where('created_at', '>=', $createdAtFrom . ' 00:00:00');
            }
            $createdAtTo = $request->input('created_at_to');
            if (!empty($createdAtTo)) {
                $query->where('created_at', '<=', $createdAtTo . ' 23:59:59');
            }

            $perPage = max(1, $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $transactions = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            // استخدام api_success مع Pagination
            if ($transactions->isEmpty()) {
                return api_success([], 'لم يتم العثور على معاملات مستخدم.');
            } else {
                return api_success(TransactionResource::collection($transactions), 'تم جلب معاملات المستخدم بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * الإيداع في صندوق نقدي.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deposit(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحية الإيداع (تندرج تحت إنشاء معاملة)
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('transactions.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإجراء إيداع.');
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => ['required', 'exists:cash_boxes,id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    $cashBox = CashBox::with(['company'])->find($value);
                    if (!$cashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)) {
                        $fail('صندوق النقد المحدد غير صالح أو غير متاح.');
                    }
                }],
                'description' => 'nullable|string',
            ]);

            DB::beginTransaction();
            try {
                $authUser->deposit(
                    $validated['amount'],
                    $validated['cash_box_id'],
                    $validated['description'] ?? null
                );

                DB::commit();
                return api_success([], 'تم الإيداع بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('فشل الإيداع. يرجى المحاولة مرة أخرى.', [], 500);
            }
        } catch (ValidationException $e) {
            return api_error('فشل التحقق من صحة البيانات أثناء الإيداع.', $e->errors(), 422);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * السحب من صندوق نقدي.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdraw(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحية السحب (تندرج تحت إنشاء معاملة)
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('transactions.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإجراء سحب.');
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => ['required', 'exists:cash_boxes,id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    $cashBox = CashBox::with(['company'])->find($value);
                    if (!$cashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)) {
                        $fail('صندوق النقد المحدد غير صالح أو غير متاح.');
                    }
                }],
                'description' => 'nullable|string',
            ]);

            DB::beginTransaction();
            try {
                // تحقق من الرصيد قبل السحب
                $currentBalance = $authUser->balanceBox($validated['cash_box_id']);
                if ($currentBalance < $validated['amount']) {
                    DB::rollBack();
                    return api_error('الرصيد غير كافٍ في صندوق النقد المحدد.', [], 422);
                }

                $authUser->withdraw(
                    $validated['amount'],
                    $validated['cash_box_id'],
                    $validated['description'] ?? null
                );

                DB::commit();
                return api_success([], 'تم السحب بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('فشل السحب. يرجى المحاولة مرة أخرى.', [], 500);
            }
        } catch (ValidationException $e) {
            return api_error('فشل التحقق من صحة البيانات أثناء السحب.', $e->errors(), 422);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * عرض جميع المعاملات مع الفلاتر والصلاحيات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transactions(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $query = Transaction::with($this->relations);

            // تطبيق شروط الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // استرجاع جميع المعاملات (لا قيود إضافية)
            } elseif ($authUser->hasAnyPermission([perm_key('transactions.view_all'), perm_key('admin.company')])) {
                // يرى جميع المعاملات ضمن الشركة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('transactions.view_children'))) {
                // يرى المعاملات التي أنشأها هو أو المستخدمون التابعون له، ضمن الشركة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('transactions.view_self'))) {
                // يرى المعاملات التي أنشأها المستخدم فقط، ومرتبطة بالشركة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض المعاملات.');
            }

            // فلاتر البحث
            if ($request->has('created_at_from')) {
                $createdAtFrom = $request->get('created_at_from');
                if ($createdAtFrom) {
                    $query->where('created_at', '>=', $createdAtFrom . ' 00:00:00');
                }
            }
            if ($request->has('created_at_to')) {
                $createdAtTo = $request->get('created_at_to');
                if ($createdAtTo) {
                    $query->where('created_at', '<=', $createdAtTo . ' 23:59:59');
                }
            }
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            if ($request->filled('cashbox_id')) {
                $query->where('cashbox_id', $request->input('cashbox_id'));
            }

            // تحديد الترتيب
            $sortField = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortField, $sortOrder);

            // تقسيم النتائج إلى صفحات
            $perPage = max(1, $request->get('per_page', 10));
            $transactions = $query->paginate($perPage);

            // استخدام api_success مع Pagination
            return api_success($transactions, 'تم استرداد المعاملات بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * عكس معاملة معينة.
     *
     * @param string $transactionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function reverseTransaction(string $transactionId)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            DB::beginTransaction();

            try {
                // استرجاع المعاملة المطلوبة وتحميل العلاقات للتحقق من الصلاحيات
                $transaction = Transaction::with(['user', 'targetUser', 'cashbox', 'targetCashbox', 'company', 'creator'])->findOrFail($transactionId);

                // التحقق من الصلاحيات بناءً على الأذونات
                $canReverse = false;
                if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                    $canReverse = true;
                } elseif ($authUser->hasAnyPermission([perm_key('transactions.update_all'), perm_key('admin.company')])) {
                    $canReverse = $transaction->belongsToCurrentCompany();
                } elseif ($authUser->hasPermissionTo(perm_key('transactions.update_children'))) {
                    $canReverse = $transaction->belongsToCurrentCompany() && $transaction->createdByUserOrChildren();
                } elseif ($authUser->hasPermissionTo(perm_key('transactions.update_self'))) {
                    $canReverse = $transaction->belongsToCurrentCompany() && $transaction->createdByCurrentUser();
                } else {
                    return api_forbidden('ليس لديك إذن لعكس هذه المعاملة.');
                }

                if (!$canReverse) {
                    return api_forbidden('ليس لديك إذن لعكس هذه المعاملة.');
                }

                // التحقق من نوع المعاملة
                switch ($transaction->type) {
                    case 'transfer':
                        $transaction->reverseTransfer();
                        break;
                    case 'withdraw':
                        $transaction->reverseWithdraw();
                        break;
                    case 'deposit':
                        $transaction->reverseDeposit();
                        break;
                    default:
                        throw new \Exception('نوع المعاملة غير مدعوم للعكس.');
                }

                // تسجيل المعاملة العكسية في جدول المعاملات
                $reversedTransaction = Transaction::create([
                    'user_id' => $transaction->target_user_id,
                    'cashbox_id' => $transaction->target_cashbox_id,
                    'target_user_id' => $transaction->user_id,
                    'target_cashbox_id' => $transaction->cashbox_id,
                    'created_by' => $authUser->id,
                    'company_id' => $companyId,
                    'type' => 'عكس',
                    'amount' => $transaction->amount,
                    'balance_before' => null,
                    'balance_after' => null,
                    'description' => 'عكس المعاملة الأصلية رقم: ' . $transaction->id,
                    'original_transaction_id' => $transaction->id,
                ]);

                DB::commit();
                return api_success(new TransactionResource($reversedTransaction), 'تم عكس المعاملة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('فشل عكس المعاملة. يرجى المحاولة مرة أخرى.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}

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
use Illuminate\Support\Facades\Log; // تم تصحيح هذا الخطأ
use Illuminate\Support\Facades\Auth; // تم تصحيح هذا الخطأ
use Throwable; // استخدام Throwable للتعامل الشامل مع الأخطاء والاستثناءات

// دالة مساعدة لضمان الاتساق في مفاتيح الأذونات (إذا لم تكن معرفة عالميا)
// if (!function_exists('perm_key')) {
//     function perm_key(string $permission): string
//     {
//         return $permission;
//     }
// }

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
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
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
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // صلاحية خاصة لتحويل الأموال (موازية لـ transactions.create)
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('transactions.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You do not have permission to transfer funds.'], 403);
            }

            $validated = $request->validate([
                'target_user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'from_cash_box_id' => ['required', 'exists:cash_boxes,id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    $cashBox = CashBox::with(['company'])->find($value);
                    if (!$cashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)) {
                        $fail('The selected source cash box is invalid or not accessible.');
                    }
                }],
                'to_cash_box_id' => ['required', 'exists:cash_boxes,id', 'different:from_cash_box_id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    $toCashBox = CashBox::with(['company'])->find($value);
                    if (!$toCashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $toCashBox->company_id !== $companyId)) {
                        $fail('The selected target cash box is invalid or not accessible.');
                    }
                }],
                'description' => 'nullable|string',
            ]);

            $targetUser = User::where('id', $validated['target_user_id'])->first();

            if (!$targetUser) {
                return response()->json(['error' => 'Not Found', 'message' => 'Target user not found.'], 404);
            }

            $fromCashBox = CashBox::findOrFail($validated['from_cash_box_id']);
            $toCashBox = CashBox::findOrFail($validated['to_cash_box_id']);

            // التأكد من أن الصناديق تابعة لشركة المستخدم (أو أن المستخدم super_admin)
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                if (!$fromCashBox->belongsToCurrentCompany() || !$toCashBox->belongsToCurrentCompany()) {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You can only transfer funds between cash boxes within your company.'], 403);
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
                Log::info('Funds transferred successfully.', [
                    'from_user_id' => $authUser->id,
                    'to_user_id' => $targetUser->id,
                    'amount' => $validated['amount'],
                    'from_cash_box_id' => $validated['from_cash_box_id'],
                    'to_cash_box_id' => $validated['to_cash_box_id'],
                    'company_id' => $companyId,
                ]);
                return response()->json(['message' => 'Transfer successful'], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Funds transfer failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Transfer failed. Please try again.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (ValidationException $e) {
            Log::error('Funds transfer validation failed: ' . $e->getMessage(), [
                'errors' => $e->errors(),
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed during fund transfer.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Funds transfer failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Transfer failed. An unexpected error occurred.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
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
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
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
            $sortField = $request->get('sort_by', 'created_at'); // الترتيب الافتراضي حسب التاريخ
            $sortOrder = $request->get('sort_order', 'desc');

            $transactions = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            return TransactionResource::collection($transactions)
                ->additional([
                    'total' => $transactions->total(),
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                ]);
        } catch (Throwable $e) {
            Log::error('User transactions retrieval failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving user transactions.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
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
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // صلاحية الإيداع (تندرج تحت إنشاء معاملة)
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('transactions.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to make a deposit.'], 403);
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => ['required', 'exists:cash_boxes,id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    $cashBox = CashBox::with(['company'])->find($value);
                    if (!$cashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)) {
                        $fail('The selected cash box is invalid or not accessible.');
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
                Log::info('Deposit successful.', [
                    'user_id' => $authUser->id,
                    'amount' => $validated['amount'],
                    'cash_box_id' => $validated['cash_box_id'],
                    'company_id' => $companyId,
                ]);
                return response()->json(['message' => 'Deposit successful'], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Deposit failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Deposit failed. Please try again.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (ValidationException $e) {
            Log::error('Deposit validation failed: ' . $e->getMessage(), [
                'errors' => $e->errors(),
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed during deposit.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Deposit failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Deposit failed. An unexpected error occurred.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
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
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // صلاحية السحب (تندرج تحت إنشاء معاملة)
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('transactions.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to make a withdrawal.'], 403);
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => ['required', 'exists:cash_boxes,id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    $cashBox = CashBox::with(['company'])->find($value);
                    if (!$cashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)) {
                        $fail('The selected cash box is invalid or not accessible.');
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
                    return response()->json(['error' => 'Insufficient funds', 'message' => 'Not enough balance in the selected cash box.'], 422);
                }

                $authUser->withdraw(
                    $validated['amount'],
                    $validated['cash_box_id'],
                    $validated['description'] ?? null
                );

                DB::commit();
                Log::info('Withdrawal successful.', [
                    'user_id' => $authUser->id,
                    'amount' => $validated['amount'],
                    'cash_box_id' => $validated['cash_box_id'],
                    'company_id' => $companyId,
                ]);
                return response()->json(['message' => 'Withdrawal successful'], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Withdrawal failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Withdrawal failed. Please try again.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (ValidationException $e) {
            Log::error('Withdrawal validation failed: ' . $e->getMessage(), [
                'errors' => $e->errors(),
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed during withdrawal.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Withdrawal failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Withdrawal failed. An unexpected error occurred.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
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
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
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
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view transactions.'], 403);
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
            $sortField = $request->get('sort_by', 'created_at'); // الحقل المراد الترتيب بناءً عليه
            $sortOrder = $request->get('sort_order', 'desc'); // نوع الترتيب (تصاعدي/تنازلي)
            $query->orderBy($sortField, $sortOrder);

            // تقسيم النتائج إلى صفحات
            $perPage = max(1, $request->get('per_page', 10)); // عدد العناصر في الصفحة
            $transactions = $query->paginate($perPage);

            // تنسيق البيانات للإرجاع
            return response()->json([
                'data' => TransactionResource::collection($transactions)->items(),
                'total' => $transactions->total(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Transactions index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving transactions.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
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
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            DB::beginTransaction();

            try {
                // استرجاع المعاملة المطلوبة وتحميل العلاقات للتحقق من الصلاحيات
                $transaction = Transaction::with(['user', 'targetUser', 'cashbox', 'targetCashbox', 'company', 'creator'])->findOrFail($transactionId);

                // التحقق من الصلاحيات بناءً على الأذونات
                $canReverse = false;
                if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                    $canReverse = true; // المسؤول العام يمكنه عكس أي معاملة
                } elseif ($authUser->hasAnyPermission([perm_key('transactions.update_any'), perm_key('admin.company')])) {
                    // يستطيع عكس المعاملات داخل الشركة النشطة
                    $canReverse = $transaction->belongsToCurrentCompany();
                } elseif ($authUser->hasPermissionTo(perm_key('transactions.update_children'))) {
                    // يستطيع عكس المعاملات التي أنشأها هو أو المستخدمون التابعون له، ضمن الشركة
                    $canReverse = $transaction->belongsToCurrentCompany() && $transaction->createdByUserOrChildren();
                } elseif ($authUser->hasPermissionTo(perm_key('transactions.update_self'))) {
                    // يستطيع عكس المعاملات التي أنشأها هو فقط، ضمن الشركة
                    $canReverse = $transaction->belongsToCurrentCompany() && $transaction->createdByCurrentUser();
                } else {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to reverse this transaction.'], 403);
                }

                if (!$canReverse) {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to reverse this transaction.'], 403);
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
                Log::info('Transaction reversed successfully.', [
                    'original_transaction_id' => $transaction->id,
                    'reversed_transaction_id' => $reversedTransaction->id,
                    'user_id' => $authUser->id,
                    'company_id' => $companyId,
                ]);

                return response()->json([
                    'message' => 'تم عكس المعاملة بنجاح',
                    'transaction' => new TransactionResource($reversedTransaction),
                ], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Transaction reversal failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'transaction_id' => $transactionId,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Failed to reverse transaction. Please try again.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Transaction reversal failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'transaction_id' => $transactionId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to reverse transaction. An unexpected error occurred.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}

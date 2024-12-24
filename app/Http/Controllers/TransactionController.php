<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class TransactionController extends Controller
{

    // داخل كنترولر المعاملات

    // تحويل الرصيد
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = auth()->user();

        // الحصول على المستخدم المستهدف
        $targetUser = User::where('id', $validated['target_user_id'])->first();

        if (!$targetUser) {
            return response()->json(['message' => 'Target user not found'], 404);
        }
        try {
            $user->transferTo($targetUser, $validated['amount']);
            return response()->json(['message' => 'Transfer successful'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }


    // الإيداع
    public function deposit(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);
        $authUser = auth()->user();

        if (
            $authUser->hasPermissionTo('deposit') ||
            $authUser->hasPermissionTo('super.admin') ||
            ($authUser->hasPermissionTo('company.owner'))
        ) {
            $user = auth()->user();
            $user->deposit($validated['amount']);
        }

        return response()->json(['message' => 'Deposit successful'], 200);
    }


    // السحب
    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = auth()->user();
        try {
            if (
                $user->hasPermissionTo('withdraw') ||
                $user->hasPermissionTo('super.admin') ||
                ($user->hasPermissionTo('company.owner'))
            ) {
                $user->withdraw($validated['amount']);
                return response()->json(['message' => 'Withdraw successful'], 200);
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }


    // عرض عمليات التحويل
    public function transactions(Request $request)
    {
        $authUser = auth()->user();

        // بناء الاستعلام الأساسي
        $query = Transaction::with(['user', 'targetUser']);

        // تطبيق شروط الصلاحيات
        if ($authUser->hasPermission('super.admin') || $authUser->hasPermission('transactions.all')) {
            // استرجاع جميع المعاملات
        } elseif ($authUser->hasPermission('company.owner')) {
            $query->whereHas('user', function ($userQuery) use ($authUser) {
                $userQuery->where('company_id', $authUser->company_id);
            });
        } elseif ($authUser->hasPermission('transactions.all.own')) {
            $query->whereHas('user', function ($userQuery) use ($authUser) {
                $userQuery->where('created_by', $authUser->id);
            });
        } else {
            $query->where('user_id', $authUser->id);
        }

        // تصفية النتائج بفترة زمنية
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

        // تحديد الترتيب
        $sortField = $request->get('sort_by', 'id'); // الحقل المراد الترتيب بناءً عليه
        $sortOrder = $request->get('sort_order', 'asc'); // نوع الترتيب (تصاعدي/تنازلي)
        $query->orderBy($sortField, $sortOrder);

        // تقسيم النتائج إلى صفحات
        $perPage = max(1, $request->get('per_page', 10)); // عدد العناصر في الصفحة
        $transactions = $query->paginate($perPage);

        // تنسيق البيانات للإرجاع
        return response()->json([
            'data' => $transactions->items(),
            'total' => $transactions->total(),
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
        ]);
    }

    public function reverseTransaction($transactionId)
    {
        $authUser = auth()->user(); // استرجاع المستخدم الحالي

        // استخدام المعاملة لضمان تكامل البيانات
        DB::beginTransaction();

        try {
            // استرجاع المعاملة المطلوبة
            $transaction = Transaction::findOrFail($transactionId);

            // التحقق من الصلاحيات بناءً على الأذونات
            if ($authUser->hasPermission('super.admin') || $authUser->hasPermission('transactions.all')) {
                // يمكنه عكس أي معاملة
            } elseif ($authUser->hasPermission('company.owner')) {
                // يستطيع عكس المعاملات التي صاحبها belong إلى نفس الشركة
                if ($transaction->user->company_id !== $authUser->company_id) {
                    throw new Exception("ليس لديك صلاحية لعكس هذه المعاملة.");
                }
            } elseif ($authUser->hasPermission('transactions.all.own')) {
                // يستطيع عكس المعاملات التي أنشأها
                if ($transaction->user->created_by !== $authUser->id) {
                    throw new Exception("ليس لديك صلاحية لعكس هذه المعاملة.");
                }
            } elseif ($transaction->user_id !== $authUser->id) {
                // يستطيع صاحب المعاملة فقط عكس المعاملة
                throw new Exception("ليس لديك صلاحية لعكس هذه المعاملة.");
            }

            // التحقق من نوع المعاملة
            switch ($transaction->type) {
                case 'transfer':
                    // عكس عملية التحويل
                    $transaction->reverseTransfer();
                    break;

                case 'withdraw':
                    // عكس عملية السحب
                    $transaction->reverseWithdraw();
                    break;

                case 'deposit':
                    // عكس عملية الإيداع
                    $transaction->reverseDeposit();
                    break;

                default:
                    throw new Exception("نوع المعاملة غير مدعوم.");
            }

            // تسجيل المعاملة العكسية في جدول المعاملات
            $reversedTransaction = Transaction::create([
                'type' => 'reversal',
                'amount' => $transaction->amount,
                'user_id' => $transaction->target_user_id,
                'target_user_id' => $transaction->user_id,
                'original_transaction_id' => $transaction->id,
            ]);

            DB::commit();

            return response()->json(['message' => 'تم عكس المعاملة بنجاح', 'transaction' => $reversedTransaction]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

}

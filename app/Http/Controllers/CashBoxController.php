<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashBox\StoreCashBoxRequest;
use App\Http\Requests\CashBox\UpdateCashBoxRequest;
use App\Http\Resources\CashBox\CashBoxResource;
use App\Models\CashBox;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Class CashBoxController
 *
 * تحكم في عمليات الخزن (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class CashBoxController extends Controller
{
    /**
     * عرض جميع الخزن مع الفلاتر والصلاحيات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            /** @var \App\Models\User|null $authUser */
            $authUser = Auth::user();
            $cashBoxQuery = CashBox::query();
            if ($request->query('Current_user') == 1) {
                $cashBoxQuery->where('user_id', $authUser->id)->whereCompanyIsCurrent();
            } else {
                $cashBoxQuery->whereDoesntHave('typeBox', function ($query) {
                    $query->where('description', 'النوع الافتراضي للسيستم');
                });
                // التحقق من صلاحيات المستخدم
                if ($authUser->hasAnyPermission(['cashbox_all', 'company_owner', 'super_admin'])) {
                    $cashBoxQuery->whereCompanyIsCurrent();
                } elseif ($authUser->hasPermissionTo('cashbox_all_own')) {
                    $cashBoxQuery->createdBySubUsers()->whereCompanyIsCurrent();
                } elseif ($authUser->hasPermissionTo('cashbox_all_self')) {
                    $cashBoxQuery->createdByUser()->whereCompanyIsCurrent();
                } else {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
                }
            }

            // التصفية باستخدام الحقول المقدمة
            // if (!empty($request->get('name'))) {
            //     $cashBoxQuery->where('name', 'like', '%' . $request->get('name') . '%');
            // }

            // if (!empty($request->get('description'))) {
            //     $cashBoxQuery->where('description', 'like', '%' . $request->get('description') . '%');
            // }

            // if (!empty($request->get('account_number'))) {
            //     $cashBoxQuery->where('account_number', 'like', '%' . $request->get('account_number') . '%');
            // }

            // if (!empty($request->get('created_at_from'))) {
            //     $cashBoxQuery->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            // }

            // if (!empty($request->get('created_at_to'))) {
            //     $cashBoxQuery->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            // }

            // تحديد عدد العناصر في الصفحة والفرز
            // $perPage = max(1, $request->get('per_page', 10));
            // $sortField = $request->get('sort_by', 'id');
            // $sortOrder = $request->get('sort_order', 'asc');

            // $cashBoxQuery->orderBy($sortField, $sortOrder);

            // جلب البيانات مع التصفية والصفحات
            $cashBoxes = $cashBoxQuery->with('typeBox')->get();

            return response()->json(CashBoxResource::collection($cashBoxes));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * تخزين خزنة جديدة.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        if ($authUser->hasAnyPermission(['super_admin', 'cashbox_create', 'company_owner'])) {
            $validated = $request->validate([
                'name' => 'required',
                'company_id' => 'required|exists:companies,id',
                'created_by' => 'nullable',
            ]);

            $validated['created_by'] = $validated['created_by'] ?? $authUser->id;

            try {
                DB::beginTransaction();
                $cashBox = CashBox::create($validated);

                DB::commit();
                return response()->json(new CashBoxResource($cashBox), 201);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        }

        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }

    /**
     * عرض تفاصيل خزنة معينة.
     *
     * @param CashBox $cashBox
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(CashBox $cashBox)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        if (
            $authUser->hasPermissionTo('company_owner') && $cashBox->isCompany() ||
            $authUser->hasPermissionTo('cashbox_show_own') && $cashBox->isOwn() ||
            $authUser->hasPermissionTo('cashbox_show_self') && $cashBox->isSelf()
        ) {
            return response()->json($cashBox);
        } else {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
        }
    }

    /**
     * تحديث بيانات خزنة موجودة.
     *
     * @param Request $request
     * @param CashBox $cashBox
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, CashBox $cashBox)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        $validated = $request->validate([
            'name' => "required|unique:cash_boxes,name,{$cashBox->id}",
            'company_id' => 'required|exists:companies,id',
            'created_by' => 'nullable',
        ]);
        $validated['created_by'] = $validated['created_by'] ?? $authUser->id;

        if (
            $authUser->hasAnyPermission(['super_admin', 'cashbox_update']) ||
            ($authUser->hasPermissionTo('company_owner') && $cashBox->isCompany()) ||
            ($authUser->hasPermissionTo('cashbox_update_own') && $cashBox->isOwn()) ||
            ($authUser->hasPermissionTo('cashbox_update_self') && $cashBox->isSelf())
        ) {
            try {
                DB::beginTransaction();
                $cashBox->update($validated);
                DB::commit();
                return response()->json($cashBox, 201);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        }
        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }

    /**
     * حذف خزنة.
     *
     * @param CashBox $cashBox
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(CashBox $cashBox)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();

        if (
            $authUser->hasAnyPermission(['super_admin', 'cashbox_delete']) ||
            $authUser->hasPermissionTo('company_owner') ||
            $authUser->hasPermissionTo('cashbox_delete_own') ||
            $authUser->hasPermissionTo('cashbox_delete_self')
        ) {
            try {
                DB::beginTransaction();
                $cashBox->delete();
                DB::commit();
                return response()->json(['message' => 'Cash box deleted successfully'], 200);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        }

        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }

    /**
     * تحويل أموال بين الخزن.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transferFunds(Request $request)
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $toUser = User::findOrFail($request->to_user_id);
        $amount = $request->amount;
        $cashBoxId = $request->cashBoxId;
        $to_cashBoxId = $request->to_cashBoxId;
        $cashBox = CashBox::findOrFail($cashBoxId);
        $to_cashBox = CashBox::findOrFail($to_cashBoxId);
        $description = $request->description;

        if (blank($request->description)) {
            if ($authUser->id == $toUser->id) {
                $description = "تحويل داخلي بين  {$cashBox->name} إلى {$to_cashBox->name}";
            } else {
                $description = "تحويل من {$authUser->nickname} إلى {$toUser->nickname}";
            }
        }
        if (!$authUser->hasAnyPermission(['super_admin', 'transfer', 'company_owner'])) {
            return response()->json(['error' => 'You do not have permission to transfer funds.'], 403);
        }
        $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'to_cashBoxId' => 'nullable|exists:cash_boxes,id',
            'cashBoxId' => 'nullable|exists:cash_boxes,id',
            'description' => 'nullable|string',
        ]);
        $authUserBalance = $authUser->balanceBox($cashBoxId);
        $toUserBalance = $toUser->balanceBox($to_cashBoxId);
        // return $request;
        DB::beginTransaction();
        try {
            // إضافة السجل الخاص بالمستخدم المخصوم منه
            Transaction::create([
                'user_id' => $authUser->id,
                'cashbox_id' => $cashBoxId,
                'target_user_id' => $toUser->id,
                'target_cashbox_id' => $to_cashBoxId,
                'type' => 'تحويل',
                'amount' => $amount,
                'description' => $description,
                'created_by' => $authUser->id,
                'company_id' => $authUser->company_id,
                'balance_before' => $authUserBalance,
                'balance_after' => $authUserBalance - $amount,
            ]);
            if ($authUser->id !== $toUser->id) {
                // إضافة السجل الخاص بالمستخدم المستلم
                Transaction::create([
                    'user_id' => $toUser->id,
                    'cashbox_id' => $to_cashBoxId,
                    'target_cashbox_id' => $cashBoxId,
                    'target_user_id' => $authUser->id,
                    'type' => 'استلام',
                    'amount' => $amount,
                    'description' => " استلام من  {$authUser->nickname}",
                    'created_by' => $authUser->id,
                    'company_id' => $authUser->company_id,
                    'balance_before' => $toUserBalance,
                    'balance_after' => $toUserBalance + $amount,
                ]);
            }
            $authUser->withdraw($amount, $cashBoxId);
            $toUser->deposit($amount, $to_cashBoxId);
            DB::commit();

            return response()->json(['message' => 'Funds transferred successfully!'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Transfer failed. Please try again.',
                'error' => $e->getMessage(),
                'trace' => $e->getTrace()
            ], 500);
        }
    }
}

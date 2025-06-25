<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CashBoxType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Class CashBoxTypeController
 *
 * تحكم في أنواع الخزن (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class CashBoxTypeController extends Controller
{
    /**
     * عرض جميع أنواع الخزن مع الفلاتر والصلاحيات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            // التحقق من صلاحيات المستخدم
            if ($authUser->hasAnyPermission(['CashBoxType_all', 'super_admin'])) {
                // المستخدم يملك صلاحية الوصول لجميع أنواع الخزنة
                $query = CashBoxType::query();
            } elseif ($authUser->hasPermissionTo('CashBoxType_show_own')) {
                // المستخدم يملك صلاحية الوصول لأنواع الخزنة الخاصة به
                $query = CashBoxType::where('created_by', $authUser->id);
            } elseif ($authUser->hasPermissionTo('CashBoxType_show_self')) {
                // المستخدم يملك صلاحية عرض نوع الخزنة الخاص به فقط
                $query = CashBoxType::where('created_by', $authUser->id);
            } else {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to access this resource.'
                ], 403);
            }

            // التصفية باستخدام الحقول المقدمة
            if (!empty($request->get('description'))) {
                $query->where('description', 'like', '%' . $request->get('description') . '%');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');

            $query->orderBy($sortField, $sortOrder);

            // جلب البيانات مع التصفية والصفحات
            $cashBoxTypes = $query->paginate($perPage);

            return response()->json([
                'data' => $cashBoxTypes->items(),
                'total' => $cashBoxTypes->total(),
                'current_page' => $cashBoxTypes->currentPage(),
                'last_page' => $cashBoxTypes->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created CashBoxType in storage.
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        // التحقق من صلاحيات المستخدم
        if (!$authUser->hasAnyPermission(['super_admin', 'CashBoxType_create'])) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
        }

        // التحقق من البيانات المدخلة
        $validatedData = $request->validate([
            'description' => 'required|string',
            'is_default' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // إنشاء نوع الخزنة
            $cashBoxType = CashBoxType::create([
                'description' => $validatedData['description'],
                'is_default' => $validatedData['is_default'] ?? false,
                'created_by' => $authUser->id,
            ]);

            DB::commit();
            return response()->json($cashBoxType, 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Failed to create CashBoxType'], 500);
        }
    }

    /**
     * Display the specified CashBoxType.
     */
    public function show(CashBoxType $cashBoxType)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        // التحقق من صلاحيات المستخدم
        if ($authUser->hasAnyPermission(['super_admin', 'CashBoxType_show'])) {
            return response()->json($cashBoxType);
        }

        if ($authUser->hasPermissionTo('CashBoxType_show_own') && $cashBoxType->created_by === $authUser->id) {
            return response()->json($cashBoxType);
        }

        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }

    /**
     * Update the specified CashBoxType in storage.
     */
    public function update(Request $request, CashBoxType $cashBoxType)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        // التحقق من صلاحيات المستخدم
        if (!$authUser->hasAnyPermission(['super_admin', 'CashBoxType_update'])) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
        }

        $validatedData = $request->validate([
            'description' => 'required|string',
            'is_default' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // تحديث نوع الخزنة
            $cashBoxType->update($validatedData);

            DB::commit();
            return response()->json($cashBoxType);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Failed to update CashBoxType'], 500);
        }
    }

    /**
     * Remove the specified CashBoxType from storage.
     */
    public function destroy(Request $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        $cashBoxTypeIds = $request->input('item_ids');

        if (!$cashBoxTypeIds || !is_array($cashBoxTypeIds)) {
            return response()->json(['error' => 'Invalid CashBoxType IDs provided'], 400);
        }

        $cashBoxTypesToDelete = CashBoxType::whereIn('id', $cashBoxTypeIds)->get();

        foreach ($cashBoxTypesToDelete as $cashBoxType) {
            if (
                $authUser->hasAnyPermission(['super_admin', 'CashBoxType_delete']) ||
                ($authUser->hasPermissionTo('CashBoxType_delete_own') && $cashBoxType->created_by === $authUser->id)
            ) {
                continue;
            }

            return response()->json(['error' => 'You do not have permission to delete CashBoxType with ID: ' . $cashBoxType->id], 403);
        }

        try {
            DB::beginTransaction();
            foreach ($cashBoxTypesToDelete as $cashBoxType) {
                $cashBoxType->delete();
            }
            DB::commit();
            return response()->json(['message' => 'CashBoxTypes deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Failed to delete CashBoxTypes'], 500);
        }
    }
}

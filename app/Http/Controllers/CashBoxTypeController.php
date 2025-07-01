<?php

namespace App\Http\Controllers;

use App\Models\CashBox;
use App\Models\CashBoxType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Throwable; // تم إضافة هذا الاستيراد
use Illuminate\Validation\ValidationException; // تم إضافة هذا الاستيراد

// دالة مساعدة لضمان الاتساق في مفاتيح الأذونات
if (!function_exists('perm_key')) {
    function perm_key(string $permission): string
    {
        return $permission;
    }
}

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

            if (!$authUser) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required.'
                ], 401);
            }

            $cashBoxTypeQuery = CashBoxType::query();

            // تطبيق منطق الصلاحيات العامة
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الأنواع
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.view_all'), perm_key('admin.company')])) {
                // مدير الشركة أو من لديه صلاحية 'view_all' يرى جميع أنواع شركته
                // افتراض: CashBoxType يمكن أن ترتبط بشركة (إذا كان ليس لها شركة_id، فستكون عامة)
                // يجب إضافة scopeWhereCompanyIsCurrent() في موديل CashBoxType
                $cashBoxTypeQuery->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_children'))) {
                // يرى الأنواع التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $cashBoxTypeQuery->whereCreatedByUserOrChildren()->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_self'))) {
                // يرى الأنواع التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $cashBoxTypeQuery->whereCreatedByUser()->whereCompanyIsCurrent();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view cash box types.'], 403);
            }

            // التصفية باستخدام الحقول المقدمة
            if (!empty($request->get('description'))) {
                $cashBoxTypeQuery->where('description', 'like', '%' . $request->get('description') . '%');
            }
            if (!empty($request->get('is_default'))) {
                $cashBoxTypeQuery->where('is_default', (bool) $request->get('is_default'));
            }
            if (!empty($request->get('created_at_from'))) {
                $cashBoxTypeQuery->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $cashBoxTypeQuery->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }


            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, (int) $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'desc');

            $cashBoxTypeQuery->orderBy($sortField, $sortOrder);

            // جلب البيانات مع التصفية والصفحات
            $cashBoxTypes = $cashBoxTypeQuery->paginate($perPage);

            return response()->json([
                'data' => $cashBoxTypes->items(),
                'total' => $cashBoxTypes->total(),
                'current_page' => $cashBoxTypes->currentPage(),
                'last_page' => $cashBoxTypes->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('CashBoxType index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error retrieving cash box types.', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created CashBoxType in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id; // افتراض أن أنواع الخزن يمكن أن ترتبط بالشركات

            if (!$authUser || (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // صلاحيات إنشاء نوع صندوق نقدي
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('cash_box_types.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to create cash box types.'
                ], 403);
            }

            DB::beginTransaction();
            try {
                // التحقق من البيانات المدخلة
                $validatedData = $request->validate([
                    'description' => 'required|string|max:255',
                    'is_default' => 'boolean',
                    // إذا كانت أنواع الصناديق مرتبطة بشركات:
                    'company_id' => 'nullable|exists:companies,id',
                ]);

                // تعيين company_id بناءً على صلاحيات المستخدم
                if ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id'])) {
                    // السوبر أدمن يمكنه إنشاء نوع لأي شركة يحددها
                } elseif ($companyId) {
                    // المستخدم العادي ينشئ نوعًا لشركته فقط
                    $validatedData['company_id'] = $companyId;
                } else {
                    // إذا لم يكن المستخدم سوبر أدمن وليس لديه company_id
                    unset($validatedData['company_id']); // إذا لم يكن هناك company_id للمستخدم، لا تقم بتعيينها
                }

                $validatedData['created_by'] = $authUser->id;

                $cashBoxType = CashBoxType::create($validatedData);

                DB::commit();
                Log::info('Cash Box Type created successfully.', ['cash_box_type_id' => $cashBoxType->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json($cashBoxType, 201);
            } catch (ValidationException $e) {
                DB::rollback();
                Log::error('CashBoxType store validation failed: ' . $e->getMessage(), [
                    'errors' => $e->errors(),
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            } catch (Throwable $e) {
                DB::rollback();
                Log::error('CashBoxType store failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json(['error' => 'Failed to create CashBoxType.', 'details' => $e->getMessage()], 500);
            }
        } catch (Throwable $e) {
            Log::error('CashBoxType store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to create CashBoxType.', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified CashBoxType.
     *
     * @param CashBoxType $cashBoxType
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(CashBoxType $cashBoxType)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي نوع صندوق
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.view_all'), perm_key('admin.company')])) {
                // يرى إذا كان نوع الصندوق ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $cashBoxType->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_children'))) {
                // يرى إذا كان نوع الصندوق أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_self'))) {
                // يرى إذا كان نوع الصندوق أنشأه هو وتابع للشركة النشطة
                $canView = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this cash box type.'], 403);
            }

            if ($canView) {
                return response()->json($cashBoxType);
            }

            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this cash box type.'], 403);
        } catch (Throwable $e) {
            Log::error('CashBoxType show failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'cash_box_type_id' => $cashBoxType->id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error retrieving cash box type.', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified CashBoxType in storage.
     *
     * @param Request $request
     * @param CashBoxType $cashBoxType
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, CashBoxType $cashBoxType)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي نوع
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.update_all'), perm_key('admin.company')])) {
                // يمكنه تعديل أي نوع داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $cashBoxType->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.update_children'))) {
                // يمكنه تعديل الأنواع التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.update_self'))) {
                // يمكنه تعديل نوعه الخاص الذي أنشأه وتابع للشركة النشطة
                $canUpdate = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this cash box type.'], 403);
            }

            if (!$canUpdate) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this cash box type.'], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validate([
                    'description' => 'required|string|max:255',
                    'is_default' => 'boolean',
                    // إذا كانت أنواع الصناديق مرتبطة بشركات:
                    'company_id' => 'nullable|exists:companies,id',
                ]);

                // التأكد من أن المستخدم مصرح له بتغيير company_id إذا كان سوبر أدمن
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $cashBoxType->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You cannot change a cash box type\'s company unless you are a Super Admin.'], 403);
                }
                // إذا لم يتم تحديد company_id في الطلب ولكن المستخدم سوبر أدمن، لا تغير company_id الخاصة بالصندوق الحالي
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }

                $cashBoxType->update($validatedData);

                DB::commit();
                Log::info('Cash Box Type updated successfully.', ['cash_box_type_id' => $cashBoxType->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json($cashBoxType, 200);
            } catch (ValidationException $e) {
                DB::rollback();
                Log::error('CashBoxType update validation failed: ' . $e->getMessage(), [
                    'errors' => $e->errors(),
                    'user_id' => Auth::id(),
                    'cash_box_type_id' => $cashBoxType->id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            } catch (Throwable $e) {
                DB::rollback();
                Log::error('CashBoxType update failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'cash_box_type_id' => $cashBoxType->id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json(['error' => 'Error updating cash box type.', 'details' => $e->getMessage()], 500);
            }
        } catch (Throwable $e) {
            Log::error('CashBoxType update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error updating cash box type.', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified CashBoxType from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            $cashBoxTypeIds = $request->input('item_ids');

            if (!$cashBoxTypeIds || !is_array($cashBoxTypeIds)) {
                return response()->json(['error' => 'Invalid CashBoxType IDs provided'], 400);
            }

            $cashBoxTypesToDelete = CashBoxType::whereIn('id', $cashBoxTypeIds)->get();

            DB::beginTransaction();
            try {
                foreach ($cashBoxTypesToDelete as $cashBoxType) {
                    // التحقق من صلاحيات الحذف لكل عنصر
                    $canDelete = false;
                    if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                        $canDelete = true;
                    } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.delete_all'), perm_key('admin.company')])) {
                        $canDelete = $cashBoxType->belongsToCurrentCompany();
                    } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.delete_children'))) {
                        $canDelete = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByUserOrChildren();
                    } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.delete_self'))) {
                        $canDelete = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByCurrentUser();
                    }

                    if (!$canDelete) {
                        DB::rollback();
                        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete cash box type with ID: ' . $cashBoxType->id], 403);
                    }

                    // تحقق مما إذا كان نوع الصندوق مرتبطًا بأي صندوق نقدي فعلي قبل الحذف
                    if (CashBox::where('type_box_id', $cashBoxType->id)->exists()) {
                        DB::rollback();
                        return response()->json([
                            'error' => 'Conflict',
                            'message' => 'Cannot delete cash box type. It is associated with existing cash boxes (ID: ' . $cashBoxType->id . ').',
                        ], 409);
                    }

                    $cashBoxType->delete();
                }

                DB::commit();
                Log::info('Cash Box Types deleted successfully.', ['cash_box_type_ids' => $cashBoxTypeIds, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(['message' => 'CashBoxTypes deleted successfully'], 200);
            } catch (Throwable $e) {
                DB::rollback();
                Log::error('CashBoxType deletion failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'cash_box_type_ids' => $cashBoxTypeIds,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json(['error' => 'Failed to delete CashBoxTypes.', 'details' => $e->getMessage()], 500);
            }
        } catch (Throwable $e) {
            Log::error('CashBoxType deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to delete CashBoxTypes.', 'details' => $e->getMessage()], 500);
        }
    }
}

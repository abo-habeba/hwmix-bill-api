<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\LogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

// دالة مساعدة لضمان الاتساق في مفاتيح الأذونات (إذا لم تكن معرفة عالميا)
// if (!function_exists('perm_key')) {
//     function perm_key(string $permission): string
//     {
//         return $permission;
//     }
// }

class LogController extends Controller
{
    /**
     * عرض قائمة بسجلات النشاطات.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();
            $query = ActivityLog::query();
            $companyId = $authUser->company_id;  // معرف الشركة النشطة للمستخدم

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // تطبيق فلترة الصلاحيات باستخدام الـ Scopes المخصصة
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع سجلات النشاطات (لا توجد قيود إضافية على الاستعلام)
            } elseif ($authUser->hasAnyPermission([perm_key('activity_logs.view_all'), perm_key('admin.company')])) {
                // يرى جميع سجلات النشاطات الخاصة بالشركة النشطة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('activity_logs.view_children'))) {
                // يرى سجلات النشاطات التي أنشأها المستخدم أو أحد التابعين له
                // يفترض أن هذا الـ Scope يتضمن فلترة الشركة إذا لزم الأمر
                $query->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('activity_logs.view_self'))) {
                // يرى سجلات النشاطات الخاصة بالمستخدم فقط
                // يفترض أن هذا الـ Scope يتضمن فلترة الشركة إذا لزم الأمر
                $query->whereCreatedByUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
            }

            // فلاتر الطلب الإضافية
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }

            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'desc');

            $query->orderBy($sortField, $sortOrder);

            // جلب البيانات مع التصفية والصفحات
            $logs = $query->paginate($perPage);

            // استخدام LogResource::collection مباشرة على الـ Paginator
            return LogResource::collection($logs)->additional([
                'total' => $logs->total(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Activity Log index failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'Error retrieving activity logs.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * التراجع عن سجل نشاط محدد.
     * يجب أن يكون المستخدم مصرحًا له "بحذف" السجل المحدد (أو التراجع عنه).
     *
     * @param Request $request
     * @param int $logId
     * @return \Illuminate\Http\JsonResponse
     */
    public function undo(Request $request, $logId)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة
            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            $log = ActivityLog::where('id', $logId)->first();

            // إذا لم يتم العثور على السجل أو لم يكن تابعًا للشركة النشطة للمستخدم (إلا إذا كان سوبر أدمن)
            if (!$log || ($log->company_id !== $companyId && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return response()->json(['error' => 'Activity Log not found or unauthorized.'], 404);
            }

            // التحقق من صلاحيات التراجع/الحذف
            $canUndo = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUndo = true;  // المسؤول العام يمكنه التراجع عن أي شيء
            } elseif ($authUser->hasPermissionTo(perm_key('activity_logs.delete_all'))) {
                // يمكنه التراجع عن أي سجل ضمن الشركة النشطة
                $canUndo = true;  // تم التحقق من company_id أعلاه
            } elseif ($authUser->hasPermissionTo(perm_key('activity_logs.delete_children'))) {
                // يمكنه التراجع عن سجلات أنشأها هو أو أحد التابعين له، ضمن الشركة النشطة
                $descendantUserIds = $authUser->getDescendantUserIds();
                $descendantUserIds[] = $authUser->id;  // إضافة معرف المستخدم نفسه
                $canUndo = in_array($log->created_by_user_id, $descendantUserIds);
            } elseif ($authUser->hasPermissionTo(perm_key('activity_logs.delete_self'))) {
                // يمكنه التراجع عن سجلات أنشأها هو فقط، ضمن الشركة النشطة
                $canUndo = ($log->created_by_user_id === $authUser->id);
            }

            if (!$canUndo) {
                return response()->json(['error' => 'Unauthorized to undo this activity log.'], 403);
            }

            // بدء عملية قاعدة البيانات لضمان الاتساق
            DB::beginTransaction();

            $modelClass = $log->model_type;

            // التأكد من أن موديل الفئة موجود وقابل للإنشاء/التحديث/الحذف
            if (!class_exists($modelClass)) {
                DB::rollBack();
                return response()->json(['error' => 'Target model class not found.'], 500);
            }

            // استعادة البيانات القديمة بناءً على نوع النشاط
            if ($log->action === 'deleted') {
                $model = new $modelClass();
                $restoredModel = $model->create($log->data_old);
                Log::info('Activity Log Undo: Restored ' . $modelClass . ' ID: ' . $restoredModel->id, ['log_id' => $logId, 'user_id' => $authUser->id]);
            } elseif ($log->action === 'updated') {
                $existingModel = $modelClass::find($log->model_id);
                if ($existingModel) {
                    $existingModel->update($log->data_old);
                    Log::info('Activity Log Undo: Updated ' . $modelClass . ' ID: ' . $existingModel->id . ' to previous state.', ['log_id' => $logId, 'user_id' => $authUser->id]);
                } else {
                    DB::rollBack();
                    return response()->json(['error' => 'Original record not found for update undo.'], 404);
                }
            } elseif ($log->action === 'created') {
                $existingModel = $modelClass::find($log->model_id);
                if ($existingModel) {
                    $existingModel->delete();
                    Log::info('Activity Log Undo: Deleted ' . $modelClass . ' ID: ' . $existingModel->id . ' that was created.', ['log_id' => $logId, 'user_id' => $authUser->id]);
                } else {
                    DB::rollBack();
                    return response()->json(['error' => 'Original record not found for create undo.'], 404);
                }
            } else {
                DB::rollBack();
                return response()->json(['error' => 'Unsupported activity log action for undo.'], 400);
            }

            DB::commit();
            return response()->json(['message' => 'Operation undone successfully.']);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Activity Log undo failed: ' . $e->getMessage(), ['exception' => $e, 'trace' => $e->getTraceAsString(), 'log_id' => $logId ?? null, 'user_id' => Auth::id()]);
            return response()->json([
                'error' => 'Error performing undo operation.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}

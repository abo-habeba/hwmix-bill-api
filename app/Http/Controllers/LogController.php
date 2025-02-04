<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Resources\LogResource;
use App\Models\Scopes\CompanyScope;

class LogController extends Controller
{
    // Logs
    // 'logs', // صفحة السجلات
    // 'logs_all', // عرض جميع السجلات
    // 'logs_all_own', // عرض السجلات التابعة له
    // 'logs_all_self', // عرض السجلات الخاصة به
    // 'logs_show', // عرض تفاصيل أي سجل
    // 'logs_show_own', // عرض تفاصيل السجلات التابعة له
    // 'logs_show_self', // عرض تفاصيل السجلات الخاصة به
    // 'logs_create', // إنشاء سجل
    // 'logs_update', // تعديل سجل
    // 'logs_update_own', // تعديل السجلات التابعة له
    // 'logs_update_self', // تعديل السجلات الخاصة به
    // 'logs_delete', // حذف السجلات
    // 'logs_delete_own', // حذف السجلات التابعة له
    // 'logs_delete_self', // حذف السجلات الخاصة به

    public function index(Request $request)
    {
        try {
            $authUser = auth()->user();
            $query = ActivityLog::query();


            if ($authUser->hasAnyPermission(['logs_all', 'company_owner', 'super_admin'])) {
                $query->company();
            } elseif ($authUser->hasPermissionTo('logs_show_own')) {
                $query->own();
            } elseif ($authUser->hasPermissionTo('logs_show_self')) {
                $query->self();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
            }

            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }

            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');

            $query->orderBy($sortField, $sortOrder);

            // جلب البيانات مع التصفية والصفحات
            $querys = $query->paginate($perPage);

            return response()->json([
                'data' => LogResource::collection($querys->items()),
                'total' => $querys->total(),
                'current_page' => $querys->currentPage(),
                'last_page' => $querys->lastPage(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function undo(Request $request, $logId)
    {
        $log = ActivityLog::findOrFail($logId);
        // استعادة البيانات القديمة
        $modelClass = $log->model;
        $model = new $modelClass();
        if ($log->action === 'deleted') {
            // إعادة إنشاء السجل المحذوف
            $model->create($log->data_old);
        } elseif ($log->action === 'updated') {
            // إعادة تحديث السجل إلى البيانات القديمة
            $existingModel = $modelClass::find($log->data_old['id']);
            $existingModel->update($log->data_old);
        } elseif ($log->action === 'created') {
            // حذف السجل الذي تم إنشاؤه
            $existingModel = $modelClass::find($log->data_new['id']);
            $existingModel->delete();
        }

        return response()->json(['message' => 'Operation undone successfully']);
    }
}

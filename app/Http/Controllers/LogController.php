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
    // 'logs.all', // عرض جميع السجلات
    // 'logs.all.own', // عرض السجلات التابعة له
    // 'logs.all.self', // عرض السجلات الخاصة به
    // 'logs.show', // عرض تفاصيل أي سجل
    // 'logs.show.own', // عرض تفاصيل السجلات التابعة له
    // 'logs.show.self', // عرض تفاصيل السجلات الخاصة به
    // 'logs.create', // إنشاء سجل
    // 'logs.update', // تعديل سجل
    // 'logs.update.own', // تعديل السجلات التابعة له
    // 'logs.update.self', // تعديل السجلات الخاصة به
    // 'logs.delete', // حذف السجلات
    // 'logs.delete.own', // حذف السجلات التابعة له
    // 'logs.delete.self', // حذف السجلات الخاصة به

    public function index(Request $request)
    {
        try {
            $authUser = auth()->user();
            $query = ActivityLog::query();


            if ($authUser->hasAnyPermission(['logs.all', 'company.owner', 'super.admin'])) {
                $query->company();
            } elseif ($authUser->hasPermissionTo('logs.show.own')) {
                $query->own();
            } elseif ($authUser->hasPermissionTo('logs.show.self')) {
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

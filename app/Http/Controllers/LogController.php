<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\LogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class LogController extends Controller
{
    /**
     * عرض قائمة بسجلات النشاطات.
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

            $query = ActivityLog::query();
            $companyId = $authUser->company_id ?? null; // معرف الشركة النشطة للمستخدم

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
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
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('activity_logs.view_self'))) {
                // يرى سجلات النشاطات الخاصة بالمستخدم فقط
                // يفترض أن هذا الـ Scope يتضمن فلترة الشركة إذا لزم الأمر
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض سجلات النشاطات.');
            }

            // فلاتر الطلب الإضافية
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }

            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, (int) $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'desc');

            $logs = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($logs->isEmpty()) {
                return api_success([], 'لم يتم العثور على سجلات نشاطات.');
            } else {
                return api_success(LogResource::collection($logs), 'تم جلب سجلات النشاطات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
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
    public function undo(Request $request, int $logId): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة
            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $log = ActivityLog::where('id', $logId)->first();

            // إذا لم يتم العثور على السجل
            if (!$log) {
                return api_not_found('سجل النشاط غير موجود.');
            }

            // إذا لم يكن السجل تابعًا للشركة النشطة للمستخدم (إلا إذا كان سوبر أدمن)
            if ($log->company_id !== $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_forbidden('سجل النشاط غير مصرح بالوصول إليه لشركتك.');
            }

            // التحقق من صلاحيات التراجع/الحذف
            $canUndo = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUndo = true; // المسؤول العام يمكنه التراجع عن أي شيء
            } elseif ($authUser->hasPermissionTo(perm_key('activity_logs.delete_all'))) {
                // يمكنه التراجع عن أي سجل ضمن الشركة النشطة
                $canUndo = true; // تم التحقق من company_id أعلاه
            } elseif ($authUser->hasPermissionTo(perm_key('activity_logs.delete_children'))) {
                // يمكنه التراجع عن سجلات أنشأها هو أو أحد التابعين له، ضمن الشركة النشطة
                $descendantUserIds = $authUser->getDescendantUserIds();
                $descendantUserIds[] = $authUser->id; // إضافة معرف المستخدم نفسه
                $canUndo = in_array($log->created_by_user_id, $descendantUserIds);
            } elseif ($authUser->hasPermissionTo(perm_key('activity_logs.delete_self'))) {
                // يمكنه التراجع عن سجلات أنشأها هو فقط، ضمن الشركة النشطة
                $canUndo = ($log->created_by_user_id === $authUser->id);
            }

            if (!$canUndo) {
                return api_forbidden('ليس لديك صلاحية للتراجع عن سجل النشاط هذا.');
            }

            // بدء عملية قاعدة البيانات لضمان الاتساق
            DB::beginTransaction();
            try {
                $modelClass = $log->model_type;

                // التأكد من أن موديل الفئة موجود وقابل للإنشاء/التحديث/الحذف
                if (!class_exists($modelClass)) {
                    DB::rollBack();
                    return api_error('فئة النموذج الهدف غير موجودة.', [], 500);
                }

                $affectedModel = null;

                // استعادة البيانات القديمة بناءً على نوع النشاط
                if ($log->action === 'deleted') {
                    $model = new $modelClass();
                    $restoredModel = $model->create($log->data_old);
                    $affectedModel = $restoredModel;
                } elseif ($log->action === 'updated') {
                    $existingModel = $modelClass::find($log->model_id);
                    if ($existingModel) {
                        $existingModel->update($log->data_old);
                        $affectedModel = $existingModel;
                    } else {
                        DB::rollBack();
                        return api_not_found('السجل الأصلي غير موجود للتراجع عن التحديث.');
                    }
                } elseif ($log->action === 'created') {
                    $existingModel = $modelClass::find($log->model_id);
                    if ($existingModel) {
                        // نسخ الكائن قبل حذفه لإرجاعه في الاستجابة
                        $deletedModel = $existingModel->replicate();
                        // قد تحتاج إلى نسخ العلاقات إذا كانت ضرورية في المورد
                        // $deletedModel->setRelations($existingModel->getRelations());
                        $existingModel->delete();
                        $affectedModel = $deletedModel;
                    } else {
                        DB::rollBack();
                        return api_not_found('السجل الأصلي غير موجود للتراجع عن الإنشاء.');
                    }
                } else {
                    DB::rollBack();
                    return api_error('إجراء سجل النشاط غير مدعوم للتراجع.', [], 400);
                }

                DB::commit();
                // إذا كان النموذج المتأثر هو مورد، قم بتغليفه بمورد مناسب
                if ($affectedModel instanceof \Illuminate\Database\Eloquent\Model) {
                    return api_success(new LogResource($log), 'تم التراجع عن العملية بنجاح.');
                }

                return api_success([], 'تم التراجع عن العملية بنجاح.'); // في حال عدم وجود نموذج متأثر مباشر للعودة
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

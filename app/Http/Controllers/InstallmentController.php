<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installment\StoreInstallmentRequest;
use App\Http\Requests\Installment\UpdateInstallmentRequest;
use App\Http\Resources\Installment\InstallmentResource;
use App\Models\Installment;
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

class InstallmentController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'installmentPlan',
            'user',  // المستخدم الذي يخصه القسط
            'creator',  // المستخدم الذي أنشأ القسط
            'payments',
            'company',  // يجب تحميل الشركة للتحقق من belongsToCurrentCompany
        ];
    }

    /**
     * عرض قائمة بالأقساط.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();
            $query = Installment::with($this->relations);
            $companyId = $authUser->company_id;  // معرف الشركة النشطة للمستخدم

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الأقساط (لا توجد قيود إضافية على الاستعلام)
            } elseif ($authUser->hasAnyPermission([perm_key('installments.view_all'), perm_key('admin.company')])) {
                // يرى جميع الأقساط الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.view_children'))) {
                // يرى الأقساط التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.view_self'))) {
                // يرى الأقساط التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view installments.'], 403);
            }

            // التصفية بناءً على طلب المستخدم
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('due_date_from')) {
                $query->where('due_date', '>=', $request->input('due_date_from'));
            }
            if ($request->filled('due_date_to')) {
                $query->where('due_date', '<=', $request->input('due_date_to'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            if ($request->filled('invoice_id')) {
                $query->where('invoice_id', $request->input('invoice_id'));
            }
            // يمكنك إضافة المزيد من فلاتر البحث هنا

            // الترتيب
            $sortBy = $request->get('sort_by', 'due_date');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');

            // التصفحة
            $perPage = (int) $request->get('limit', 20);
            $installments = $query->paginate($perPage);

            return InstallmentResource::collection($installments)->additional([
                'total' => $installments->total(),
                'current_page' => $installments->currentPage(),
                'last_page' => $installments->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Installment index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),  // استخدام Auth::id() بدلا من $authUser->id مباشرة هنا تحسبا لحالة الخطأ قبل تعيين $authUser
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error retrieving installments.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),  // تم تصحيح هذا الخطأ
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreInstallmentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreInstallmentRequest $request)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة
            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // صلاحيات إنشاء قسط
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('installments.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to create installments.'
                ], 403);
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // تعيين created_by و company_id تلقائيًا
                $validatedData['created_by'] = $authUser->id;
                // التأكد من أن القسط تابع لشركة المستخدم الحالي
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $companyId) {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You can only create installments for your current company.'], 403);
                }
                $validatedData['company_id'] = $companyId;  // التأكد من ربط القسط بالشركة النشطة

                $installment = Installment::create($validatedData);
                $installment->load($this->relations);
                DB::commit();
                Log::info('Installment created successfully.', ['installment_id' => $installment->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new InstallmentResource($installment);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Installment store failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'error' => 'Error saving installment.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),  // تم تصحيح هذا الخطأ
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Installment store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error saving installment.',
                'details' => $e->getMessage(),  // تم تصحيح هذا الخطأ
                'file' => $e->getFile(),
                'line' => $e->getLine(),  // تم تصحيح هذا الخطأ
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Installment\InstallmentResource
     */
    public function show($id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            $installment = Installment::with($this->relations)->findOrFail($id);

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;  // المسؤول العام يرى أي قسط
            } elseif ($authUser->hasAnyPermission([perm_key('installments.view_all'), perm_key('admin.company')])) {
                // يرى إذا كان القسط ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $installment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.view_children'))) {
                // يرى إذا كان القسط أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $installment->belongsToCurrentCompany() && $installment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.view_self'))) {
                // يرى إذا كان القسط أنشأه هو وتابع للشركة النشطة
                $canView = $installment->belongsToCurrentCompany() && $installment->createdByCurrentUser();
            } else {
                // لا توجد صلاحية عرض
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this installment.'], 403);
            }

            if ($canView) {
                return new InstallmentResource($installment);
            }

            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this installment.'], 403);
        } catch (Throwable $e) {
            Log::error('Installment show failed: ' . $e->getMessage(), [
                'exception' => $e,  // تم تصحيح هذا الخطأ
                'user_id' => Auth::id(),
                'installment_id' => $id,
                'file' => $e->getFile(),  // تم تصحيح هذا الخطأ
                'line' => $e->getLine(),  // تم تصحيح هذا الخطأ
            ]);
            return response()->json([
                'error' => 'Error retrieving installment.',
                'details' => $e->getMessage(),  // تم تصحيح هذا الخطأ
                'file' => $e->getFile(),  // تم تصحيح هذا الخطأ
                'line' => $e->getLine(),  // تم تصحيح هذا الخطأ
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateInstallmentRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse|\App\Http\Resources\Installment\InstallmentResource
     */
    public function update(UpdateInstallmentRequest $request, $id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $installment = Installment::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;  // المسؤول العام يمكنه تعديل أي قسط
            } elseif ($authUser->hasAnyPermission([perm_key('installments.update_any'), perm_key('admin.company')])) {
                // يمكنه تعديل أي قسط داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $installment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.update_children'))) {
                // يمكنه تعديل الأقساط التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $installment->belongsToCurrentCompany() && $installment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.update_self'))) {
                // يمكنه تعديل قسطه الخاص الذي أنشأه وتابع للشركة النشطة
                $canUpdate = $installment->belongsToCurrentCompany() && $installment->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this installment.'], 403);
            }

            if (!$canUpdate) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this installment.'], 403);
            }

            DB::beginTransaction();
            try {
                $installment->update($request->validated());
                $installment->load($this->relations);  // إعادة تحميل العلاقات بعد التحديث
                DB::commit();
                Log::info('Installment updated successfully.', ['installment_id' => $installment->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return new InstallmentResource($installment);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Installment update failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'installment_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'error' => 'Error updating installment.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Installment update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'installment_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error updating installment.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
            }

            // يجب تحميل العلاقات الضرورية للتحقق من الصلاحيات (مثل الشركة والمنشئ)
            $installment = Installment::with(['company', 'creator'])->findOrFail($id);

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;  // المسؤول العام يمكنه حذف أي قسط
            } elseif ($authUser->hasAnyPermission([perm_key('installments.delete_any'), perm_key('admin.company')])) {
                // يمكنه حذف أي قسط داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $installment->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.delete_children'))) {
                // يمكنه حذف الأقساط التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $installment->belongsToCurrentCompany() && $installment->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('installments.delete_self'))) {
                // يمكنه حذف قسطه الخاص الذي أنشأه وتابع للشركة النشطة
                $canDelete = $installment->belongsToCurrentCompany() && $installment->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this installment.'], 403);
            }

            if (!$canDelete) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this installment.'], 403);
            }

            DB::beginTransaction();
            try {
                $installment->delete();
                DB::commit();
                Log::info('Installment deleted successfully.', ['installment_id' => $id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(['message' => 'Deleted successfully'], 200);
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error('Installment deletion failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'installment_id' => $id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'error' => 'Error deleting installment.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('Installment deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'installment_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Error deleting installment.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),  // تم تصحيح هذا الخطأ
            ], 500);
        }
    }
}

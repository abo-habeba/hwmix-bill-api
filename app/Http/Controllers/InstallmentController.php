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
use Illuminate\Support\Facades\Log;  // لإضافة تسجيل الأخطاء
use Throwable;  // للتعامل الشامل مع الاستثناءات

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
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();

            if (!$authUser) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required.'
                ], 401);
            }

            $query = Installment::with($this->relations);

            // تطبيق منطق الصلاحيات
            if ($authUser->hasAnyPermission(['installments_all', 'super_admin'])) {
                // لا حاجة لتطبيق أي scope خاص
            } elseif ($authUser->hasPermissionTo('company_owner')) {
                // إذا كان مالك شركة، طبق scopeCompany
                $query->scopeCompany();  // يفترض أن هذا الـ scope يربط الأقساط بالشركات
            } elseif ($authUser->hasPermissionTo('installments_show_own')) {
                // للأقساط التي أنشأها المستخدم
                $query->scopeOwn();
            } elseif ($authUser->hasPermissionTo('installments_show_self')) {
                // للأقساط التي تخص المستخدم نفسه (إذا كان القسط مرتبطًا مباشرة بـ user_id للمستخدم)
                $query->scopeSelf();
            } else {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to view installments.'
                ], 403);
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
                'user_id' => $authUser->id,
            ]);
            return response()->json([
                'error' => true,
                'message' => 'حدث خطأ أثناء جلب الأقساط.',
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInstallmentRequest $request)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        // صلاحيات إنشاء قسط
        // يجب أن يكون لديك صلاحية عامة لإنشاء أقساط، أو أن تكون مالك شركة
        if (!$authUser->hasAnyPermission(['installments_create', 'super_admin', 'company_owner'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to create installments.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $validatedData = $request->validated();

            // تعيين creator_id و company_id تلقائيًا
            $validatedData['created_by'] = $authUser->id;
            // إذا كان القسط تابع لشركة، تأكد من ربطه بالشركة الصحيحة
            // إذا كان المستخدم company_owner، يجب أن يكون القسط تابعًا لشركته
            if ($authUser->hasPermissionTo('company_owner') && !isset($validatedData['company_id'])) {
                $validatedData['company_id'] = $authUser->company_id;
            }

            $installment = Installment::create($validatedData);
            $installment->load($this->relations);
            DB::commit();
            return new InstallmentResource($installment);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Installment store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $authUser->id,
            ]);
            return response()->json([
                'message' => 'حدث خطأ أثناء حفظ القسط.',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        $query = Installment::where('id', $id)->with($this->relations);

        // تطبيق منطق الصلاحيات لجلب القسط
        if ($authUser->hasAnyPermission(['installments_show', 'installments_all', 'super_admin'])) {
            // لا حاجة لـ scope خاص
        } elseif ($authUser->hasPermissionTo('company_owner')) {
            $query->scopeCompany();
        } elseif ($authUser->hasPermissionTo('installments_show_own')) {
            $query->scopeOwn();
        } elseif ($authUser->hasPermissionTo('installments_show_self')) {
            $query->scopeSelf();
        } else {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to view this installment.'
            ], 403);
        }

        $installment = $query->first();

        if (!$installment) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Installment not found or you do not have permission to view it.'
            ], 404);
        }

        return new InstallmentResource($installment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInstallmentRequest $request, $id)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        $query = Installment::where('id', $id);

        // تطبيق منطق الصلاحيات قبل التحديث
        if ($authUser->hasAnyPermission(['installments_update', 'installments_all', 'super_admin'])) {
            // لا حاجة لـ scope خاص
        } elseif ($authUser->hasPermissionTo('company_owner')) {
            $query->scopeCompany();
        } elseif ($authUser->hasPermissionTo('installments_update_own')) {
            $query->scopeOwn();
        } elseif ($authUser->hasPermissionTo('installments_update_self')) {
            $query->scopeSelf();
        } else {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to update this installment.'
            ], 403);
        }

        $installment = $query->first();

        if (!$installment) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Installment not found or you do not have permission to update it.'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $installment->update($request->validated());
            $installment->load($this->relations);
            DB::commit();
            return new InstallmentResource($installment);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Installment update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $authUser->id,
            ]);
            return response()->json([
                'message' => 'حدث خطأ أثناء تحديث القسط.',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.'
            ], 401);
        }

        $query = Installment::where('id', $id);

        // تطبيق منطق الصلاحيات قبل الحذف
        if ($authUser->hasAnyPermission(['installments_delete', 'installments_all', 'super_admin'])) {
            // لا حاجة لـ scope خاص
        } elseif ($authUser->hasPermissionTo('company_owner')) {
            $query->scopeCompany();
        } elseif ($authUser->hasPermissionTo('installments_delete_own')) {
            $query->scopeOwn();
        } elseif ($authUser->hasPermissionTo('installments_delete_self')) {
            $query->scopeSelf();
        } else {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to delete this installment.'
            ], 403);
        }

        $installment = $query->first();

        if (!$installment) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Installment not found or you do not have permission to delete it.'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $installment->delete();
            DB::commit();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Installment deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => $authUser->id,
            ]);
            return response()->json([
                'message' => 'حدث خطأ أثناء حذف القسط.',
            ], 500);
        }
    }
}

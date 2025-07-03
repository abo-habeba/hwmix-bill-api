<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Payment; // لاستخدامه في التحقق من العلاقات قبل الحذف
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentMethodController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
            'payments',  // للتحقق من المدفوعات المرتبطة قبل الحذف
        ];
    }

    /**
     * عرض قائمة طرق الدفع.
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

            $query = PaymentMethod::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع طرق الدفع
            } elseif ($authUser->hasAnyPermission([perm_key('payment_methods.view_all'), perm_key('admin.company')])) {
                // يرى جميع طرق الدفع الخاصة بالشركة النشطة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.view_children'))) {
                // يرى طرق الدفع التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.view_self'))) {
                // يرى طرق الدفع التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض طرق الدفع.');
            }

            // فلاتر الطلب الإضافية
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->input('name') . '%');
            }
            if ($request->filled('code')) {
                $query->where('code', $request->input('code'));
            }
            if ($request->filled('active')) {
                $query->where('active', (bool) $request->input('active'));
            }
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, (int) $request->input('per_page', 20));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');

            $paymentMethods = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($paymentMethods->isEmpty()) {
                return api_success($paymentMethods, 'لم يتم العثور على طرق دفع.');
            } else {
                return api_success($paymentMethods, 'تم جلب طرق الدفع بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين طريقة دفع جديدة.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('payment_methods.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء طرق دفع.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validate([
                    'name' => 'required|string|max:255',
                    'code' => 'required|string|max:255|unique:payment_methods,code',
                    'active' => 'required|boolean',
                    'company_id' => 'nullable|exists:companies,id', // السماح بتحديد الشركة للسوبر أدمن
                ]);

                $validatedData['created_by'] = $authUser->id;

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $methodCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء طريقة دفع لهذه الشركة
                if ($methodCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء طرق دفع لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }
                $validatedData['company_id'] = $methodCompanyId;

                $paymentMethod = PaymentMethod::create($validatedData);
                $paymentMethod->load($this->relations);
                DB::commit();
                return api_success($paymentMethod, 'تم إنشاء طريقة الدفع بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين طريقة الدفع.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ طريقة الدفع.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض طريقة دفع محددة.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $paymentMethod = PaymentMethod::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payment_methods.view_all'), perm_key('admin.company')])) {
                $canView = $paymentMethod->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.view_children'))) {
                $canView = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.view_self'))) {
                $canView = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByCurrentUser();
            }

            if ($canView) {
                return api_success($paymentMethod, 'تم استرداد طريقة الدفع بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض طريقة الدفع هذه.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث طريقة دفع محددة.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $paymentMethod = PaymentMethod::with(['company', 'creator'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payment_methods.update_all'), perm_key('admin.company')])) {
                $canUpdate = $paymentMethod->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.update_children'))) {
                $canUpdate = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.update_self'))) {
                $canUpdate = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث طريقة الدفع هذه.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validate([
                    'name' => 'sometimes|string|max:255',
                    'code' => 'sometimes|string|max:255|unique:payment_methods,code,' . $id,
                    'active' => 'sometimes|boolean',
                    'company_id' => 'nullable|exists:companies,id', // السماح بتحديد الشركة للسوبر أدمن
                ]);

                $validatedData['updated_by'] = $authUser->id;

                // التأكد من أن المستخدم مصرح له بتغيير company_id إذا كان سوبر أدمن
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $paymentMethod->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة طريقة الدفع إلا إذا كنت مدير عام.');
                }
                // إذا لم يتم تحديد company_id في الطلب ولكن المستخدم سوبر أدمن، لا تغير company_id الخاصة بالطريقة الحالية
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }

                $paymentMethod->update($validatedData);
                $paymentMethod->load($this->relations);
                DB::commit();
                return api_success($paymentMethod, 'تم تحديث طريقة الدفع بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث طريقة الدفع.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث طريقة الدفع.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف طريقة دفع محددة.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $paymentMethod = PaymentMethod::with(['company', 'creator', 'payments'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('payment_methods.delete_all'), perm_key('admin.company')])) {
                $canDelete = $paymentMethod->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.delete_children'))) {
                $canDelete = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('payment_methods.delete_self'))) {
                $canDelete = $paymentMethod->belongsToCurrentCompany() && $paymentMethod->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف طريقة الدفع هذه.');
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كانت طريقة الدفع مرتبطة بأي مدفوعات
                if ($paymentMethod->payments()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف طريقة الدفع. إنها مرتبطة بمدفوعات موجودة.', [], 409);
                }

                $deletedPaymentMethod = $paymentMethod->replicate();
                $deletedPaymentMethod->setRelations($paymentMethod->getRelations());

                $paymentMethod->delete();
                DB::commit();
                return api_success($deletedPaymentMethod, 'تم حذف طريقة الدفع بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Revenue\StoreRevenueRequest; // تم تحديث اسم مجلد الطلب
use App\Http\Requests\Revenue\UpdateRevenueRequest; // يجب إنشاء هذا الطلب أو استخدام StoreRevenueRequest مع قواعد "sometimes"
use App\Http\Resources\Revenue\RevenueResource; // تم تحديث اسم مجلد المورد
use App\Models\Revenue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RevenueController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
            // أضف أي علاقات أخرى ذات صلة هنا، مثل 'invoice' أو 'user' إذا كانت الإيرادات مرتبطة بها مباشرةً
        ];
    }

    /**
     * عرض قائمة الإيرادات.
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

            $query = Revenue::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الإيرادات
            } elseif ($authUser->hasAnyPermission([perm_key('revenues.view_all'), perm_key('admin.company')])) {
                // يرى جميع الإيرادات الخاصة بالشركة النشطة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('revenues.view_children'))) {
                // يرى الإيرادات التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('revenues.view_self'))) {
                // يرى الإيرادات التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض الإيرادات.');
            }

            // فلاتر الطلب الإضافية
            if ($request->filled('company_id')) {
                // تأكد من أن المستخدم لديه صلاحية رؤية الإيرادات لشركة أخرى إذا تم تحديدها
                if ($request->input('company_id') != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return api_forbidden('ليس لديك إذن لعرض الإيرادات لشركة أخرى.');
                }
                $query->where('company_id', $request->input('company_id'));
            }
            if ($request->filled('amount_from')) {
                $query->where('amount', '>=', $request->input('amount_from'));
            }
            if ($request->filled('amount_to')) {
                $query->where('amount', '<=', $request->input('amount_to'));
            }
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, (int) $request->get('per_page', 15));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');

            $revenues = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($revenues->isEmpty()) {
                return api_success([], 'لم يتم العثور على إيرادات.');
            } else {
                return api_success(RevenueResource::collection($revenues), 'تم جلب الإيرادات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين إيراد جديد.
     *
     * @param StoreRevenueRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRevenueRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('revenues.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء إيرادات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $revenueCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء إيراد لهذه الشركة
                if ($revenueCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء إيرادات لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }
                $validatedData['company_id'] = $revenueCompanyId;

                $revenue = Revenue::create($validatedData);
                $revenue->load($this->relations);
                DB::commit();
                return api_success(new RevenueResource($revenue), 'تم إنشاء الإيراد بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين الإيراد.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ الإيراد.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض إيراد محدد.
     *
     * @param Revenue $revenue
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Revenue $revenue): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $revenue->load($this->relations);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('revenues.view_all'), perm_key('admin.company')])) {
                $canView = $revenue->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('revenues.view_children'))) {
                $canView = $revenue->belongsToCurrentCompany() && $revenue->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('revenues.view_self'))) {
                $canView = $revenue->belongsToCurrentCompany() && $revenue->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new RevenueResource($revenue), 'تم استرداد الإيراد بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذا الإيراد.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث إيراد محدد.
     *
     * @param UpdateRevenueRequest $request
     * @param Revenue $revenue
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateRevenueRequest $request, Revenue $revenue): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $revenue->load(['company', 'creator']); // تحميل العلاقات للتحقق من الصلاحيات

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('revenues.update_all'), perm_key('admin.company')])) {
                $canUpdate = $revenue->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('revenues.update_children'))) {
                $canUpdate = $revenue->belongsToCurrentCompany() && $revenue->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('revenues.update_self'))) {
                $canUpdate = $revenue->belongsToCurrentCompany() && $revenue->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذا الإيراد.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                // التأكد من أن المستخدم مصرح له بتغيير company_id إذا كان سوبر أدمن
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $revenue->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة الإيراد إلا إذا كنت مدير عام.');
                }
                // إذا لم يتم تحديد company_id في الطلب ولكن المستخدم سوبر أدمن، لا تغير company_id الخاصة بالإيراد الحالي
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }

                $revenue->update($validatedData);
                $revenue->load($this->relations);
                DB::commit();
                return api_success(new RevenueResource($revenue), 'تم تحديث الإيراد بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث الإيراد.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الإيراد.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف إيراد محدد.
     *
     * @param Revenue $revenue
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Revenue $revenue): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $revenue->load(['company', 'creator']); // تحميل العلاقات للتحقق من الصلاحيات

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('revenues.delete_all'), perm_key('admin.company')])) {
                $canDelete = $revenue->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('revenues.delete_children'))) {
                $canDelete = $revenue->belongsToCurrentCompany() && $revenue->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('revenues.delete_self'))) {
                $canDelete = $revenue->belongsToCurrentCompany() && $revenue->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذا الإيراد.');
            }

            DB::beginTransaction();
            try {
                // حفظ نسخة من الإيراد قبل حذفه لإرجاعها في الاستجابة
                $deletedRevenue = $revenue->replicate();
                $deletedRevenue->setRelations($revenue->getRelations());

                $revenue->delete();
                DB::commit();
                return api_success(new RevenueResource($deletedRevenue), 'تم حذف الإيراد بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف الإيراد.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

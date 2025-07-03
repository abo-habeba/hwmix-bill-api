<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profit\StoreProfitRequest; // تم تحديث اسم مجلد الطلب
use App\Http\Requests\Profit\UpdateProfitRequest; // تم إضافة طلب التحديث
use App\Http\Resources\Profit\ProfitResource; // تم تحديث اسم مجلد المورد
use App\Models\Profit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfitController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
            // أضف أي علاقات أخرى ذات صلة هنا، مثل 'invoiceItem' أو 'user' إذا كانت الأرباح مرتبطة بها مباشرةً
        ];
    }

    /**
     * عرض قائمة الأرباح.
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

            $query = Profit::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // تطبيق فلترة الصلاحيات بناءً على صلاحيات العرض
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الأرباح
            } elseif ($authUser->hasAnyPermission([perm_key('profits.view_all'), perm_key('admin.company')])) {
                // يرى جميع الأرباح الخاصة بالشركة النشطة
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.view_children'))) {
                // يرى الأرباح التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.view_self'))) {
                // يرى الأرباح التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض الأرباح.');
            }

            // فلاتر الطلب الإضافية
            if ($request->filled('company_id')) {
                // تأكد من أن المستخدم لديه صلاحية رؤية الأرباح لشركة أخرى إذا تم تحديدها
                if ($request->input('company_id') != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return api_forbidden('ليس لديك إذن لعرض الأرباح لشركة أخرى.');
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

            $profits = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($profits->isEmpty()) {
                return api_success([], 'لم يتم العثور على أرباح.');
            } else {
                return api_success(ProfitResource::collection($profits), 'تم جلب الأرباح بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين ربح جديد.
     *
     * @param StoreProfitRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProfitRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('profits.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء أرباح.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $profitCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء ربح لهذه الشركة
                if ($profitCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء أرباح لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }
                $validatedData['company_id'] = $profitCompanyId;

                $profit = Profit::create($validatedData);
                $profit->load($this->relations);
                DB::commit();
                return api_success(new ProfitResource($profit), 'تم إنشاء الربح بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين الربح.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ الربح.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض ربح محدد.
     *
     * @param Profit $profit
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Profit $profit): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $profit->load($this->relations);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('profits.view_all'), perm_key('admin.company')])) {
                $canView = $profit->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.view_children'))) {
                $canView = $profit->belongsToCurrentCompany() && $profit->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.view_self'))) {
                $canView = $profit->belongsToCurrentCompany() && $profit->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new ProfitResource($profit), 'تم استرداد الربح بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذا الربح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث ربح محدد.
     *
     * @param UpdateProfitRequest $request
     * @param Profit $profit
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProfitRequest $request, Profit $profit): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $profit->load(['company', 'creator']); // تحميل العلاقات للتحقق من الصلاحيات

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('profits.update_all'), perm_key('admin.company')])) {
                $canUpdate = $profit->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.update_children'))) {
                $canUpdate = $profit->belongsToCurrentCompany() && $profit->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.update_self'))) {
                $canUpdate = $profit->belongsToCurrentCompany() && $profit->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذا الربح.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                // التأكد من أن المستخدم مصرح له بتغيير company_id إذا كان سوبر أدمن
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $profit->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة الربح إلا إذا كنت مدير عام.');
                }
                // إذا لم يتم تحديد company_id في الطلب ولكن المستخدم سوبر أدمن، لا تغير company_id الخاصة بالربح الحالي
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }

                $profit->update($validatedData);
                $profit->load($this->relations);
                DB::commit();
                return api_success(new ProfitResource($profit), 'تم تحديث الربح بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث الربح.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الربح.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف ربح محدد.
     *
     * @param Profit $profit
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Profit $profit): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $profit->load(['company', 'creator']); // تحميل العلاقات للتحقق من الصلاحيات

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('profits.delete_all'), perm_key('admin.company')])) {
                $canDelete = $profit->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.delete_children'))) {
                $canDelete = $profit->belongsToCurrentCompany() && $profit->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('profits.delete_self'))) {
                $canDelete = $profit->belongsToCurrentCompany() && $profit->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذا الربح.');
            }

            DB::beginTransaction();
            try {
                // حفظ نسخة من الربح قبل حذفه لإرجاعها في الاستجابة
                $deletedProfit = $profit->replicate();
                $deletedProfit->setRelations($profit->getRelations());

                $profit->delete();
                DB::commit();
                return api_success(new ProfitResource($deletedProfit), 'تم حذف الربح بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف الربح.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

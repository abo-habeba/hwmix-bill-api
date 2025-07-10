<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceType\StoreInvoiceTypeRequest;
use App\Http\Requests\InvoiceType\UpdateInvoiceTypeRequest;
use App\Http\Resources\InvoiceType\InvoiceTypeResource;
use App\Models\InvoiceType;
use App\Models\Invoice; // لاستخدامه في التحقق من العلاقات قبل الحذف
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceTypeController extends Controller
{
    private array $relations;

    public function __construct()
    {
        $this->relations = [
            'invoices',  // العلاقة مع الفواتير المرتبطة بهذا النوع
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
        ];
    }

    /**
     * عرض قائمة أنواع الفواتير.
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

            $query = InvoiceType::query()->with($this->relations);

            // فلاتر الطلب الإضافية
            if ($request->filled('context')) {
                $query->where('context', $request->input('context'));
            }
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = (int) $request->input('per_page', 20);
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');

            $types = $query->orderBy($sortField, $sortOrder);
            $types = $perPage == -1
                ? $types->get()
                : $types->paginate(max(1, $perPage));

            if ($types->isEmpty()) {
                return api_success([], 'لم يتم العثور على أنواع فواتير.');
            } else {
                return api_success(InvoiceTypeResource::collection($types), 'تم جلب أنواع الفواتير بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين نوع فاتورة جديد.
     *
     * @param StoreInvoiceTypeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreInvoiceTypeRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('invoice_types.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك صلاحية لإنشاء أنواع فواتير.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $invoiceTypeCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء نوع فاتورة لهذه الشركة
                if ($invoiceTypeCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء أنواع فواتير لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }
                $validatedData['company_id'] = $invoiceTypeCompanyId;

                $type = InvoiceType::create($validatedData);
                $type->load($this->relations);
                DB::commit();
                return api_success(new InvoiceTypeResource($type), 'تم إنشاء نوع الفاتورة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين نوع الفاتورة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ نوع الفاتورة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض نوع فاتورة محدد.
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

            $type = InvoiceType::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoice_types.view_all'), perm_key('admin.company')])) {
                $canView = $type->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_types.view_children'))) {
                $canView = $type->belongsToCurrentCompany() && $type->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_types.view_self'))) {
                $canView = $type->belongsToCurrentCompany() && $type->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new InvoiceTypeResource($type), 'تم استرداد نوع الفاتورة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض نوع الفاتورة هذا.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث نوع فاتورة محدد.
     *
     * @param UpdateInvoiceTypeRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateInvoiceTypeRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $type = InvoiceType::with(['company', 'creator'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoice_types.update_all'), perm_key('admin.company')])) {
                $canUpdate = $type->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_types.update_children'))) {
                $canUpdate = $type->belongsToCurrentCompany() && $type->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_types.update_self'))) {
                $canUpdate = $type->belongsToCurrentCompany() && $type->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث نوع الفاتورة هذا.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                // التأكد من أن المستخدم مصرح له بتغيير company_id إذا كان سوبر أدمن
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $type->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة نوع الفاتورة إلا إذا كنت مدير عام.');
                }
                // إذا لم يتم تحديد company_id في الطلب ولكن المستخدم سوبر أدمن، لا تغير company_id الخاصة بالصندوق الحالي
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }

                $type->update($validatedData);
                $type->load($this->relations);
                DB::commit();
                return api_success(new InvoiceTypeResource($type), 'تم تحديث نوع الفاتورة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث نوع الفاتورة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث نوع الفاتورة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف نوع فاتورة محدد.
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

            $type = InvoiceType::with(['company', 'creator', 'invoices'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('invoice_types.delete_all'), perm_key('admin.company')])) {
                $canDelete = $type->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_types.delete_children'))) {
                $canDelete = $type->belongsToCurrentCompany() && $type->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('invoice_types.delete_self'))) {
                $canDelete = $type->belongsToCurrentCompany() && $type->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف نوع الفاتورة هذا.');
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كان نوع الفاتورة مرتبطًا بأي فواتير
                if ($type->invoices()->exists()) {
                    DB::rollBack();
                    return api_error('لا يمكن حذف نوع الفاتورة. إنه مرتبط بفاتورة واحدة أو أكثر.', [], 409);
                }

                $deletedType = $type->replicate(); // نسخ الكائن قبل الحذف
                $deletedType->setRelations($type->getRelations()); // نسخ العلاقات المحملة

                $type->delete();
                DB::commit();
                return api_success(new InvoiceTypeResource($deletedType), 'تم حذف نوع الفاتورة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_exception($e);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

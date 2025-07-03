<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\Service\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ServiceController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'company',
            'creator',
        ];
    }

    /**
     * عرض قائمة الخدمات.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return api_unauthorized('يتطلب المصادقة.');
            }

            $query = Service::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // فلترة الصلاحيات
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الخدمات
            } elseif ($authUser->hasAnyPermission([perm_key('services.view_all'), perm_key('admin.company')])) {
                $query->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('services.view_children'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('services.view_self'))) {
                $query->whereCompanyIsCurrent()->whereCreatedByUser();
            } else {
                return api_forbidden('ليس لديك إذن لعرض الخدمات.');
            }

            // فلاتر الطلب
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->input('name') . '%');
            }
            if ($request->filled('description')) {
                $query->where('description', 'like', '%' . $request->input('description') . '%');
            }
            if ($request->filled('default_price_from')) {
                $query->where('default_price', '>=', $request->input('default_price_from'));
            }
            if ($request->filled('default_price_to')) {
                $query->where('default_price', '<=', $request->input('default_price_to'));
            }
            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // التصفح والفرز
            $perPage = max(1, (int) $request->get('per_page', 20));
            $sortField = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'desc');

            $services = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            if ($services->isEmpty()) {
                return api_success([], 'لم يتم العثور على خدمات.');
            } else {
                return api_success(ServiceResource::collection($services), 'تم جلب الخدمات بنجاح.');
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تخزين خدمة جديدة.
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('services.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء خدمات.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['created_by'] = $authUser->id;

                $serviceCompanyId = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                if ($serviceCompanyId != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء خدمات لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }
                $validatedData['company_id'] = $serviceCompanyId;

                $service = Service::create($validatedData);
                $service->load($this->relations);
                DB::commit();
                return api_success(new ServiceResource($service), 'تم إنشاء الخدمة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين الخدمة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حفظ الخدمة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * عرض خدمة محددة.
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

            $service = Service::with($this->relations)->findOrFail($id);

            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true;
            } elseif ($authUser->hasAnyPermission([perm_key('services.view_all'), perm_key('admin.company')])) {
                $canView = $service->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('services.view_children'))) {
                $canView = $service->belongsToCurrentCompany() && $service->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('services.view_self'))) {
                $canView = $service->belongsToCurrentCompany() && $service->createdByCurrentUser();
            }

            if ($canView) {
                return api_success(new ServiceResource($service), 'تم استرداد الخدمة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه الخدمة.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تحديث خدمة محددة.
     */
    public function update(UpdateServiceRequest $request, string $id): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $service = Service::with(['company', 'creator'])->findOrFail($id);

            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true;
            } elseif ($authUser->hasAnyPermission([perm_key('services.update_all'), perm_key('admin.company')])) {
                $canUpdate = $service->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('services.update_children'))) {
                $canUpdate = $service->belongsToCurrentCompany() && $service->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('services.update_self'))) {
                $canUpdate = $service->belongsToCurrentCompany() && $service->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذه الخدمة.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();
                $validatedData['updated_by'] = $authUser->id;

                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $service->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة الخدمة إلا إذا كنت مدير عام.');
                }
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }

                $service->update($validatedData);
                $service->load($this->relations);
                DB::commit();
                return api_success(new ServiceResource($service), 'تم تحديث الخدمة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollBack();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث الخدمة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء تحديث الخدمة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * حذف خدمة محددة.
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

            $service = Service::with(['company', 'creator'])->findOrFail($id);

            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('services.delete_all'), perm_key('admin.company')])) {
                $canDelete = $service->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('services.delete_children'))) {
                $canDelete = $service->belongsToCurrentCompany() && $service->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('services.delete_self'))) {
                $canDelete = $service->belongsToCurrentCompany() && $service->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذه الخدمة.');
            }

            DB::beginTransaction();
            try {
                $deletedService = $service->replicate();
                $deletedService->setRelations($service->getRelations());

                $service->delete();
                DB::commit();
                return api_success(new ServiceResource($deletedService), 'تم حذف الخدمة بنجاح.');
            } catch (Throwable $e) {
                DB::rollBack();
                return api_error('حدث خطأ أثناء حذف الخدمة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
}

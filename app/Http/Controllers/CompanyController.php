<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Company\CompanyRequest;
use App\Http\Resources\Company\CompanyResource;
use App\Http\Requests\Company\CompanyUpdateRequest;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();
            $query = Company::query();

            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // وصول مطلق
            } elseif ($authUser->hasAnyPermission([
                perm_key('companies.view_all'),
                perm_key('admin.company'),
                perm_key('companies.view_children'),
                perm_key('companies.view_self')
            ])) {
                $query->whereIn('id', $authUser->companies->pluck('id')->toArray());
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض الشركات.');
            }

            if (!empty($request->get('created_at_from'))) {
                $query->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $query->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            $perPage = max(1, $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');
            $companies = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

            return api_success(CompanyResource::collection($companies), 'تم جلب الشركات بنجاح');
        } catch (\Exception $e) {
            return api_exception($e);
        }
    }

    public function store(CompanyRequest $request)
    {
        $authUser = Auth::user();
        if (!$authUser->hasAnyPermission([
            perm_key('admin.super'),
            perm_key('companies.create'),
            perm_key('admin.company')
        ])) {
            return api_forbidden('ليس لديك صلاحية لإنشاء شركة.');
        }

        $validatedData = $request->validated();
        try {
            DB::beginTransaction();

            $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
            $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;

            $file = $request->hasFile('logo')
                ? $request->file('logo')
                : new \Illuminate\Http\UploadedFile(public_path('images/default-logo.png'), 'default-logo.png');

            $company = Company::create($validatedData);
            $company->users()->attach($authUser->id, ['created_by' => $authUser->id]);
            $company->saveImage('logo', $file);

            Warehouse::create([
                'name' => 'المخزن الرئيسي',
                'company_id' => $company->id,
                'created_by' => $authUser->id,
                'status' => 'active',
            ]);

            $company->logCreated("بإنشاء شركة باسم {$company->name}");
            DB::commit();
            return api_success(new CompanyResource($company), 'تم إنشاء الشركة بنجاح');
        } catch (\Exception $e) {
            DB::rollback();
            return api_exception($e);
        }
    }

    public function show(Company $company)
    {
        $authUser = Auth::user();

        if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
            return api_success(new CompanyResource(Company::withoutGlobalScope(CompanyScope::class)->findOrFail($company->id)), 'تم جلب بيانات الشركة بنجاح');
        }

        if (
            $authUser->hasPermissionTo(perm_key('companies.view_all')) ||
            ($authUser->hasPermissionTo(perm_key('companies.view_children')) && $company->isOwn()) ||
            ($authUser->hasPermissionTo(perm_key('companies.view_self')) && $company->isSelf()) ||
            ($authUser->hasPermissionTo(perm_key('admin.company')) && $authUser->company_id === $company->company_id)
        ) {
            return api_success(new CompanyResource($company), 'تم جلب بيانات الشركة بنجاح');
        }

        return api_forbidden('ليس لديك صلاحية لعرض هذه الشركة.');
    }

    public function update(CompanyUpdateRequest $request, Company $company)
    {
        $authUser = Auth::user();
        $validated = $request->validated();

        if (
            $authUser->hasPermissionTo(perm_key('admin.super')) ||
            $authUser->hasPermissionTo(perm_key('companies.update_all')) ||
            ($authUser->hasPermissionTo(perm_key('companies.update_children')) && $company->isOwn()) ||
            ($authUser->hasPermissionTo(perm_key('companies.update_self')) && $company->isSelf()) ||
            ($authUser->hasPermissionTo(perm_key('admin.company')) && $authUser->company_id === $company->company_id)
        ) {
            try {
                DB::beginTransaction();
                $company->update($validated);

                if ($logoRequest = $request->file('logo')) {
                    if ($logo = $company->images()->where('type', 'logo')->first()) {
                        $company->deleteImage($logo);
                    }
                    $company->saveImage('logo', $logoRequest);
                }

                $company->logUpdated('الشركة ' . $company->name);
                DB::commit();
                return api_success(new CompanyResource($company), 'تم تحديث الشركة بنجاح');
            } catch (\Exception $e) {
                DB::rollback();
                return api_exception($e);
            }
        }

        return api_forbidden('ليس لديك صلاحية لتحديث هذه الشركة.');
    }

    public function destroy(Request $request)
    {
        $authUser = Auth::user();
        $companyIds = $request->input('item_ids');

        if (!$companyIds || !is_array($companyIds)) {
            return api_error('لم يتم تحديد معرفات الشركات بشكل صحيح', [], 400);
        }

        $companiesToDelete = Company::whereIn('id', $companyIds)->get();

        foreach ($companiesToDelete as $company) {
            if (!(
                $authUser->hasPermissionTo(perm_key('admin.super')) ||
                $authUser->hasPermissionTo(perm_key('companies.delete_all')) ||
                ($authUser->hasPermissionTo(perm_key('companies.delete_children')) && $company->isOwn()) ||
                ($authUser->hasPermissionTo(perm_key('companies.delete_self')) && $company->created_by == $authUser->id) ||
                ($authUser->hasPermissionTo(perm_key('admin.company')) && $authUser->company_id === $company->company_id)
            )) {
                return api_forbidden('ليس لديك صلاحية لحذف الشركة ذات المعرف: ' . $company->id);
            }
        }

        try {
            DB::beginTransaction();
            foreach ($companiesToDelete as $company) {
                if ($logo = $company->images()->where('type', 'logo')->first()) {
                    $company->deleteImage($logo);
                }
                $company->delete();
                $company->logForceDeleted('الشركة ' . $company->name);
            }
            DB::commit();
            return api_success([], 'تم حذف الشركات بنجاح');
        } catch (\Exception $e) {
            DB::rollback();
            return api_exception($e);
        }
    }
}

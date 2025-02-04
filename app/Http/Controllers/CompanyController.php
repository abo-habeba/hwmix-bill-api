<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Scopes\CompanyScope;
use App\Http\Requests\Company\CompanyRequest;
use App\Http\Resources\Company\CompanyResource;
use App\Http\Resources\Company\CompaniesResource;
use App\Http\Requests\Company\CompanyUpdateRequest;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $authUser = auth()->user();
            $query = Company::query();
            // 'companys_all', // جميع الشركات
            // 'companys_all_own', // الشركات التابعين له
            // 'companys_all_self', // عرض الشركات الخاص به
            if ($authUser->hasAnyPermission(['companys_all', 'company_owner', 'super_admin'])) {

            } elseif ($authUser->hasPermissionTo('companys_show_own')) {
                $query->own();
            } elseif ($authUser->hasPermissionTo('companys_show_self')) {
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


            $perPage = max(1, $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');

            $query->orderBy($sortField, $sortOrder);

            $query->whereHas('users', function ($query) use ($authUser) {
                $query->where('user_id', $authUser->id);
            });

            // جلب البيانات مع التصفية والصفحات
            $querys = $query->paginate($perPage);

            return response()->json([
                'data' => CompaniesResource::collection($querys->items()),
                'total' => $querys->total(),
                'current_page' => $querys->currentPage(),
                'last_page' => $querys->lastPage(),
            ]);

            // return response()->json([
            //     'data' => CompanyResource::collection($query->get()),
            // ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    /**
     * Store a newly created resource in storage.
     */
    // { value: 'companys_create', name: 'إنشاء شركة' },

    public function store(CompanyRequest $request)
    {
        $authUser = auth()->user();
        // 'companys_create', // إنشاء شركة
        if (!$authUser->hasAnyPermission(['super_admin', 'companys_create', 'company_owner'])) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
        }
        $validatedData = $request->validated();
        try {
            DB::beginTransaction();
            $validatedData['company_id'] = $validatedData['company_id'] ?? $authUser->company_id;
            $validatedData['created_by'] = $validatedData['created_by'] ?? $authUser->id;

            // $file = $request->file('logo');
            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
            } else {
                // تحديد لوجو بديل إذا لم يتم إرسال لوجو
                $defaultLogoPath = public_path('images/default-logo.png'); // مسار اللوجو البديل
                $file = new \Illuminate\Http\UploadedFile($defaultLogoPath, 'default-logo.png');
            }

            $company = Company::create($validatedData);
            $company->users()->attach($authUser);
            $company->saveImage('logo', $file);

            $company->logCreated(" بانشاء شركة باسم {$company->name}");
            DB::commit();
            return new CompanyResource($company);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        $authUser = auth()->user();

        if ($authUser->hasPermissionTo('company_owner')) {
            $company = Company::withoutGlobalScope(CompanyScope::class)->findOrFail($company->id);
        }
        // 'companys_show', // عرض تفاصيل أي شركة
        // 'companys_show_own', // عرض تفاصيل الشركات التابعين له
        // 'companys_show_self', // عرض تفاصيل الشركة الخاصه به
        if (
            $authUser->hasPermissionTo('companys_show') ||
            $authUser->hasPermissionTo('super_admin') ||
            ($authUser->hasPermissionTo('companys_show_own') && $authUser->id === $company->id) ||
            ($authUser->hasPermissionTo('company_owner') && $authUser->company_id === $company->company_id) ||
            $authUser->id === $company->id
        ) {
            return new CompanyResource($company);
        }

        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);
    }

    /**
     * Update the specified resource in storage.
     */

    public function update(CompanyUpdateRequest $request, Company $company)
    {
        $authUser = auth()->user();

        $validated = $request->validated();
        if ($authUser->id === $company->id) {
            unset($validated['status'], $validated['balance']);
        }

        // 'companys_update', // تعديل أي شركة
        // 'companys_update_own', // تعديل الشركات التابعين له
        // 'companys_update_self', // تعديل الشركه الخاصه به
        if (
            $authUser->hasAnyPermission(['super_admin', 'companys_update']) ||
            ($authUser->hasPermissionTo('companys_update_own') && $company->isOwn()) ||
            ($authUser->hasPermissionTo('companys_update_self') && $company->isSelf())
        ) {
            try {
                DB::beginTransaction();
                $company->update($validated);

                $logoRequest = $request->file('logo');

                if ($logoRequest) {
                    $logo = $company->images()->where('type', 'logo')->first();
                    if ($logo) {
                        $company->deleteImage($logo);
                    }
                    $company->saveImage('logo', $logoRequest);
                }

                $company->logUpdated(' الشركة  ' . $company->name);
                DB::commit();
                return new CompanyResource($company);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        }
        return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'], 403);

    }

    /**
     * Remove the specified resource from storage.
     */

    public function destroy(Request $request)
    {
        // 'companys_delete', // حذف أي شركة
        // 'companys_delete_own', // حذف الشركات التابعين له
        // 'companys_delete_self', // حذف الشركه الخاصه به

        $authUser = auth()->user();

        $companyIds = $request->input('item_ids');

        if (!$companyIds || !is_array($companyIds)) {
            return response()->json(['error' => 'Invalid company IDs provided'], 400);
        }
        $companysToDelete = Company::whereIn('id', $companyIds)->get();

        foreach ($companysToDelete as $company) {
            if (
                $authUser->hasAnyPermission(['super_admin', 'companys_delete']) ||
                ($authUser->hasPermissionTo('companys_delete_own') && $company->isOwn()) ||
                ($authUser->hasPermissionTo('companys_delete_self') && $company->created_by == $authUser->id) ||
                ($authUser->hasPermissionTo('company_owner') && $authUser->company_id === $company->company_id)
            ) {
                continue;
            }

            return response()->json(['error' => 'You do not have permission to delete company with ID: ' . $company->id], 403);
        }
        try {

            DB::beginTransaction();

            foreach ($companysToDelete as $company) {
                $logo = $company->images()->where('type', 'logo')->first();
                if ($logo) {
                    $company->deleteImage($logo);
                }
                $company->delete();
                $company->logForceDeleted(' الشركة ' . $company->name);

            }
            DB::commit();
            return response()->json(['message' => 'company deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}


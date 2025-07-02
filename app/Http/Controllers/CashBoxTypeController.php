<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CashBox;
use App\Models\CashBoxType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // للتأكد من استيراد JsonResponse
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;


/**
 * Class CashBoxTypeController
 *
 * تحكم في أنواع الخزن (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class CashBoxTypeController extends Controller
{
    /**
     * عرض جميع أنواع الخزن مع الفلاتر والصلاحيات.
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

            $cashBoxTypeQuery = CashBoxType::query();

            // تطبيق منطق الصلاحيات العامة
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                // المسؤول العام يرى جميع الأنواع
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.view_all'), perm_key('admin.company')])) {
                // مدير الشركة أو من لديه صلاحية 'view_all' يرى جميع أنواع شركته
                // يجب إضافة scopeWhereCompanyIsCurrent() في موديل CashBoxType
                $cashBoxTypeQuery->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_children'))) {
                // يرى الأنواع التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                $cashBoxTypeQuery->whereCreatedByUserOrChildren()->whereCompanyIsCurrent();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_self'))) {
                // يرى الأنواع التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                $cashBoxTypeQuery->whereCreatedByUser()->whereCompanyIsCurrent();
            } else {
                return api_forbidden('ليس لديك صلاحية لعرض أنواع الخزن.');
            }

            // التصفية باستخدام الحقول المقدمة
            if (!empty($request->get('description'))) {
                $cashBoxTypeQuery->where('description', 'like', '%' . $request->get('description') . '%');
            }
            if (!empty($request->get('is_default'))) {
                $cashBoxTypeQuery->where('is_default', (bool) $request->get('is_default'));
            }
            if (!empty($request->get('created_at_from'))) {
                $cashBoxTypeQuery->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $cashBoxTypeQuery->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, (int) $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'desc');

            $cashBoxTypeQuery->orderBy($sortField, $sortOrder);

            // جلب البيانات مع التصفية والصفحات
            $cashBoxTypes = $cashBoxTypeQuery->paginate($perPage);

            return api_success($cashBoxTypes, 'تم استرداد أنواع الخزن بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Store a newly created CashBoxType in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null; // افتراض أن أنواع الخزن يمكن أن ترتبط بالشركات

            if (!$authUser || (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء نوع صندوق نقدي
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('cash_box_types.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك صلاحية لإنشاء أنواع الخزن.');
            }

            DB::beginTransaction();
            try {
                // التحقق من البيانات المدخلة
                $validatedData = $request->validate([
                    'description' => 'required|string|max:255',
                    'is_default' => 'boolean',
                    // إذا كانت أنواع الصناديق مرتبطة بشركات:
                    'company_id' => 'nullable|exists:companies,id',
                ]);

                // تعيين company_id بناءً على صلاحيات المستخدم
                if ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id'])) {
                    // السوبر أدمن يمكنه إنشاء نوع لأي شركة يحددها
                } elseif ($companyId) {
                    // المستخدم العادي ينشئ نوعًا لشركته فقط
                    $validatedData['company_id'] = $companyId;
                } else {
                    // إذا لم يكن المستخدم سوبر أدمن وليس لديه company_id
                    unset($validatedData['company_id']); // إذا لم يكن هناك company_id للمستخدم، لا تقم بتعيينها
                }

                $validatedData['created_by'] = $authUser->id;

                $cashBoxType = CashBoxType::create($validatedData);

                DB::commit();
                return api_success($cashBoxType, 'تم إنشاء نوع الخزنة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollback();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين نوع الخزنة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollback();
                return api_error('حدث خطأ أثناء حفظ نوع الخزنة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Display the specified CashBoxType.
     *
     * @param CashBoxType $cashBoxType
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(CashBoxType $cashBoxType): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي نوع صندوق
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.view_all'), perm_key('admin.company')])) {
                // يرى إذا كان نوع الصندوق ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $cashBoxType->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_children'))) {
                // يرى إذا كان نوع الصندوق أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.view_self'))) {
                // يرى إذا كان نوع الصندوق أنشأه هو وتابع للشركة النشطة
                $canView = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByCurrentUser();
            }

            if ($canView) {
                return api_success($cashBoxType, 'تم استرداد نوع الخزنة بنجاح.');
            }

            return api_forbidden('ليس لديك صلاحية لعرض نوع الخزنة هذا.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Update the specified CashBoxType in storage.
     *
     * @param Request $request
     * @param CashBoxType $cashBoxType
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, CashBoxType $cashBoxType): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي نوع
            } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.update_all'), perm_key('admin.company')])) {
                // يمكنه تعديل أي نوع داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $cashBoxType->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.update_children'))) {
                // يمكنه تعديل الأنواع التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.update_self'))) {
                // يمكنه تعديل نوعه الخاص الذي أنشأه وتابع للشركة النشطة
                $canUpdate = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك صلاحية لتحديث نوع الخزنة هذا.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validate([
                    'description' => 'required|string|max:255',
                    'is_default' => 'boolean',
                    // إذا كانت أنواع الصناديق مرتبطة بشركات:
                    'company_id' => 'nullable|exists:companies,id',
                ]);

                // التأكد من أن المستخدم مصرح له بتغيير company_id إذا كان سوبر أدمن
                if (isset($validatedData['company_id']) && $validatedData['company_id'] != $cashBoxType->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    return api_forbidden('لا يمكنك تغيير شركة نوع الخزنة إلا إذا كنت مدير عام.');
                }
                // إذا لم يتم تحديد company_id في الطلب ولكن المستخدم سوبر أدمن، لا تغير company_id الخاصة بالصندوق الحالي
                if (!$authUser->hasPermissionTo(perm_key('admin.super')) || !isset($validatedData['company_id'])) {
                    unset($validatedData['company_id']);
                }

                $cashBoxType->update($validatedData);

                DB::commit();
                return api_success($cashBoxType, 'تم تحديث نوع الخزنة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollback();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث نوع الخزنة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollback();
                return api_error('حدث خطأ أثناء تحديث نوع الخزنة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * Remove the specified CashBoxType from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super')))) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            $cashBoxTypeIds = $request->input('item_ids');

            if (!$cashBoxTypeIds || !is_array($cashBoxTypeIds)) {
                return api_error('تم توفير معرفات أنواع الخزن غير صالحة.', [], 400);
            }

            $cashBoxTypesToDelete = CashBoxType::whereIn('id', $cashBoxTypeIds)->get();

            DB::beginTransaction();
            try {
                $deletedTypes = [];
                foreach ($cashBoxTypesToDelete as $cashBoxType) {
                    // التحقق من صلاحيات الحذف لكل عنصر
                    $canDelete = false;
                    if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                        $canDelete = true;
                    } elseif ($authUser->hasAnyPermission([perm_key('cash_box_types.delete_all'), perm_key('admin.company')])) {
                        $canDelete = $cashBoxType->belongsToCurrentCompany();
                    } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.delete_children'))) {
                        $canDelete = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByUserOrChildren();
                    } elseif ($authUser->hasPermissionTo(perm_key('cash_box_types.delete_self'))) {
                        $canDelete = $cashBoxType->belongsToCurrentCompany() && $cashBoxType->createdByCurrentUser();
                    }

                    if (!$canDelete) {
                        DB::rollback();
                        return api_forbidden('ليس لديك صلاحية لحذف نوع الخزنة بالمعرف: ' . $cashBoxType->id);
                    }

                    // تحقق مما إذا كان نوع الصندوق مرتبطًا بأي صندوق نقدي فعلي قبل الحذف
                    if (CashBox::where('type_box_id', $cashBoxType->id)->exists()) {
                        DB::rollback();
                        return api_error('لا يمكن حذف نوع الخزنة. إنه مرتبط بخزن نقدية موجودة (المعرف: ' . $cashBoxType->id . ').', [], 409);
                    }

                    // حفظ نسخة من العنصر قبل حذفه لإرجاعه في الاستجابة
                    $deletedType = $cashBoxType->replicate();
                    $deletedType->setRelations($cashBoxType->getRelations()); // نسخ العلاقات المحملة
                    $deletedTypes[] = $deletedType;

                    $cashBoxType->delete();
                }

                DB::commit();
                return api_success($deletedTypes, 'تم حذف أنواع الخزن بنجاح.');
            } catch (Throwable $e) {
                DB::rollback();
                return api_error('حدث خطأ أثناء حذف أنواع الخزن.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }
}

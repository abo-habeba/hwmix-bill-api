<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashBox\StoreCashBoxRequest;
use App\Http\Requests\CashBox\UpdateCashBoxRequest;
use App\Http\Resources\CashBox\CashBoxResource;
use App\Models\CashBox;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // للتأكد من استيراد JsonResponse
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Class CashBoxController
 *
 * تحكم في عمليات الخزن (عرض، إضافة، تعديل، حذف)
 *
 * @package App\Http\Controllers
 */
class CashBoxController extends Controller
{
    protected array $relations;

    public function __construct()
    {
        $this->relations = [
            'typeBox',
            'company',   // للتحقق من belongsToCurrentCompany
            'creator',   // للتحقق من createdByCurrentUser/OrChildren
            'user',      // المستخدم الذي يخصه الصندوق (إذا كان هناك حقل user_id في الصندوق)
        ];
    }

    /**
     * عرض جميع الخزن مع الفلاتر والصلاحيات.
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

            $cashBoxQuery = CashBox::query()->with($this->relations);
            $companyId = $authUser->company_id ?? null;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return api_unauthorized('المستخدم غير مرتبط بشركة.');
            }

            // منطق خاص لعرض صناديق المستخدم الحالي فقط
            if ($request->query('current_user') == 1) {
                $cashBoxQuery->where('user_id', $authUser->id)->whereCompanyIsCurrent();
            } else {
                // فلترة الصناديق الافتراضية للنظام (إذا لم تكن لـ current_user)
                $cashBoxQuery->whereDoesntHave('typeBox', function ($query) {
                    $query->where('description', 'النوع الافتراضي للسيستم');
                });

                // تطبيق منطق الصلاحيات العامة
                if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                    // المسؤول العام يرى جميع الصناديق (لا قيود إضافية)
                } elseif ($authUser->hasAnyPermission([perm_key('cash_boxes.view_all'), perm_key('admin.company')])) {
                    // يرى جميع الصناديق الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                    $cashBoxQuery->whereCompanyIsCurrent();
                } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.view_children'))) {
                    // يرى الصناديق التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                    $cashBoxQuery->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
                } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.view_self'))) {
                    // يرى الصناديق التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                    $cashBoxQuery->whereCompanyIsCurrent()->whereCreatedByUser();
                } else {
                    return api_forbidden('ليس لديك إذن لعرض الخزن.');
                }
            }

            // التصفية باستخدام الحقول المقدمة
            if (!empty($request->get('name'))) {
                $cashBoxQuery->where('name', 'like', '%' . $request->get('name') . '%');
            }
            if (!empty($request->get('description'))) {
                $cashBoxQuery->where('description', 'like', '%' . $request->get('description') . '%');
            }
            if (!empty($request->get('account_number'))) {
                $cashBoxQuery->where('account_number', 'like', '%' . $request->get('account_number') . '%');
            }
            if (!empty($request->get('created_at_from'))) {
                $cashBoxQuery->where('created_at', '>=', $request->get('created_at_from') . ' 00:00:00');
            }
            if (!empty($request->get('created_at_to'))) {
                $cashBoxQuery->where('created_at', '<=', $request->get('created_at_to') . ' 23:59:59');
            }
            if (!empty($request->get('user_id'))) { // فلتر جديد لتحديد الصناديق الخاصة بمستخدم معين
                $cashBoxQuery->where('user_id', $request->get('user_id'));
            }

            // تحديد عدد العناصر في الصفحة والفرز
            $perPage = max(1, (int) $request->get('per_page', 10));
            $sortField = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'desc'); // عادة ما يكون الأحدث أولاً

            $cashBoxQuery->orderBy($sortField, $sortOrder);

            // جلب البيانات مع التصفية والصفحات
            $cashBoxes = $cashBoxQuery->paginate($perPage);

            return api_success($cashBoxes, 'تم استرداد الخزن بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تخزين خزنة جديدة.
     *
     * @param StoreCashBoxRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCashBoxRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحيات إنشاء صندوق نقدي
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('cash_boxes.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لإنشاء خزن.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // إذا كان المستخدم super_admin ويحدد company_id، يسمح بذلك. وإلا، استخدم company_id للمستخدم.
                $validatedData['company_id'] = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $companyId;

                // التأكد من أن المستخدم مصرح له بإنشاء صندوق لهذه الشركة
                if ($validatedData['company_id'] != $companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('يمكنك فقط إنشاء خزن لشركتك الحالية ما لم تكن مسؤولاً عامًا.');
                }

                $validatedData['created_by'] = $authUser->id;
                // يمكن إضافة 'active' أو حالات افتراضية أخرى هنا إذا كانت موجودة في النموذج
                // $validatedData['active'] = (bool) ($validatedData['active'] ?? true);

                $cashBox = CashBox::create($validatedData);
                $cashBox->load($this->relations);
                DB::commit();
                return api_success(new CashBoxResource($cashBox), 'تم إنشاء الخزنة بنجاح.', 201);
            } catch (ValidationException $e) {
                DB::rollback();
                return api_error('فشل التحقق من صحة البيانات أثناء تخزين الخزنة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollback();
                return api_error('حدث خطأ أثناء حفظ الخزنة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * عرض تفاصيل خزنة معينة.
     *
     * @param CashBox $cashBox
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(CashBox $cashBox): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي صندوق
            } elseif ($authUser->hasAnyPermission([perm_key('cash_boxes.view_all'), perm_key('admin.company')])) {
                // يرى إذا كان الصندوق ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $cashBox->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.view_children'))) {
                // يرى إذا كان الصندوق أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $cashBox->belongsToCurrentCompany() && $cashBox->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.view_self'))) {
                // يرى إذا كان الصندوق أنشأه هو وتابع للشركة النشطة
                $canView = $cashBox->belongsToCurrentCompany() && $cashBox->createdByCurrentUser();
            }

            if ($canView) {
                $cashBox->load($this->relations); // تحميل العلاقات
                return api_success(new CashBoxResource($cashBox), 'تم استرداد تفاصيل الخزنة بنجاح.');
            }

            return api_forbidden('ليس لديك إذن لعرض هذه الخزنة.');
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تحديث بيانات خزنة موجودة.
     *
     * @param UpdateCashBoxRequest $request
     * @param CashBox $cashBox
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateCashBoxRequest $request, CashBox $cashBox): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي صندوق
            } elseif ($authUser->hasAnyPermission([perm_key('cash_boxes.update_all'), perm_key('admin.company')])) {
                // يمكنه تعديل أي صندوق داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $cashBox->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.update_children'))) {
                // يمكنه تعديل الصناديق التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $cashBox->belongsToCurrentCompany() && $cashBox->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.update_self'))) {
                // يمكنه تعديل صندوقه الخاص الذي أنشأه وتابع للشركة النشطة
                $canUpdate = $cashBox->belongsToCurrentCompany() && $cashBox->createdByCurrentUser();
            }

            if (!$canUpdate) {
                return api_forbidden('ليس لديك إذن لتحديث هذه الخزنة.');
            }

            DB::beginTransaction();
            try {
                $validatedData = $request->validated();

                // إذا كان المستخدم سوبر ادمن ويحدد معرف الشركه، يسمح بذلك. وإلا، استخدم معرف الشركه للصندوق.
                $validatedData['company_id'] = ($authUser->hasPermissionTo(perm_key('admin.super')) && isset($validatedData['company_id']))
                    ? $validatedData['company_id']
                    : $cashBox->company_id;

                // التأكد من أن المستخدم مصرح له بتعديل صندوق لهذه الشركة
                if ($validatedData['company_id'] != $cashBox->company_id && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                    DB::rollBack();
                    return api_forbidden('لا يمكنك تغيير شركة الخزنة ما لم تكن مسؤولاً عامًا.');
                }

                // $validatedData['active'] = (bool) ($validatedData['active'] ?? $cashBox->active); // إذا كان هناك حقل نشط

                $cashBox->update($validatedData);
                $cashBox->load($this->relations);
                DB::commit();
                return api_success(new CashBoxResource($cashBox), 'تم تحديث الخزنة بنجاح.');
            } catch (ValidationException $e) {
                DB::rollback();
                return api_error('فشل التحقق من صحة البيانات أثناء تحديث الخزنة.', $e->errors(), 422);
            } catch (Throwable $e) {
                DB::rollback();
                return api_error('حدث خطأ أثناء تحديث الخزنة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * حذف خزنة.
     *
     * @param CashBox $cashBox
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(CashBox $cashBox): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true;
            } elseif ($authUser->hasAnyPermission([perm_key('cash_boxes.delete_all'), perm_key('admin.company')])) {
                // يمكنه حذف أي صندوق داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $cashBox->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.delete_children'))) {
                // يمكنه حذف الصناديق التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $cashBox->belongsToCurrentCompany() && $cashBox->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.delete_self'))) {
                // يمكنه حذف صندوقه الخاص الذي أنشأه وتابع للشركة النشطة
                $canDelete = $cashBox->belongsToCurrentCompany() && $cashBox->createdByCurrentUser();
            }

            if (!$canDelete) {
                return api_forbidden('ليس لديك إذن لحذف هذه الخزنة.');
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كان الصندوق مرتبطًا بأي معاملات قبل الحذف
                if (Transaction::where('cashbox_id', $cashBox->id)->exists() || Transaction::where('target_cashbox_id', $cashBox->id)->exists()) {
                    DB::rollback();
                    return api_error('لا يمكن حذف الخزنة. إنها تحتوي على معاملات مرتبطة.', [], 409);
                }

                // حفظ نسخة من الخزنة قبل حذفها لإرجاعها في الاستجابة
                $deletedCashBox = $cashBox->replicate();
                $deletedCashBox->setRelations($cashBox->getRelations()); // نسخ العلاقات المحملة

                $cashBox->delete();
                DB::commit();
                return api_success(new CashBoxResource($deletedCashBox), 'تم حذف الخزنة بنجاح.');
            } catch (Throwable $e) {
                DB::rollback();
                return api_error('حدث خطأ أثناء حذف الخزنة.', [], 500);
            }
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    /**
     * تحويل أموال بين الخزن.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transferFunds(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id ?? null;

            if (!$authUser || !$companyId) {
                return api_unauthorized('يتطلب المصادقة أو الارتباط بالشركة.');
            }

            // صلاحية خاصة لتحويل الأموال
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('cash_boxes.transfer_funds')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return api_forbidden('ليس لديك إذن لتحويل الأموال.');
            }

            $validated = $request->validate([
                'to_user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => ['required', 'exists:cash_boxes,id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    // تأكد أن الصندوق ينتمي لشركة المستخدم أو أن المستخدم super_admin
                    $cashBox = CashBox::with(['company', 'creator'])->find($value);
                    if (!$cashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)) {
                        $fail('صندوق النقد المحدد غير صالح أو غير متاح.');
                    }
                }],
                'to_cash_box_id' => ['required', 'exists:cash_boxes,id', 'different:cash_box_id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    // تأكد أن الصندوق الهدف ينتمي لشركة المستخدم أو أن المستخدم super_admin
                    $toCashBox = CashBox::with(['company', 'creator'])->find($value);
                    if (!$toCashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $toCashBox->company_id !== $companyId)) {
                        $fail('صندوق النقد المستهدف غير صالح أو غير متاح.');
                    }
                }],
                'description' => 'nullable|string',
            ]);

            $toUser = User::findOrFail($validated['to_user_id']);
            $amount = $validated['amount'];
            $fromCashBoxId = $validated['cash_box_id'];
            $toCashBoxId = $validated['to_cash_box_id'];

            $fromCashBox = CashBox::with(['company'])->findOrFail($fromCashBoxId);
            $toCashBox = CashBox::with(['company'])->findOrFail($toCashBoxId);

            // التحقق من أن الصناديق ضمن الشركة التي يمكن للمستخدم الوصول إليها (خاصة لغير الـ super_admin)
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                if (!$fromCashBox->belongsToCurrentCompany() || !$toCashBox->belongsToCurrentCompany()) {
                    return api_forbidden('يمكنك فقط تحويل الأموال بين الخزن داخل شركتك.');
                }
            }

            // تحقق من رصيد الصندوق المصدر
            $authUserBalance = $authUser->balanceBox($fromCashBoxId);
            if ($authUserBalance < $amount) {
                return api_error('الرصيد غير كافٍ في صندوق النقد المصدر لهذا التحويل.', [], 422);
            }

            // وصف التحويل
            $description = $validated['description'];
            if (empty($description)) { // استخدام empty بدلاً من blank
                if ($authUser->id == $toUser->id) {
                    $description = "تحويل داخلي بين {$fromCashBox->name} إلى {$toCashBox->name}";
                } else {
                    $description = "تحويل من {$authUser->nickname} إلى {$toUser->nickname}";
                }
            }

            DB::beginTransaction();
            try {
                // إضافة السجل الخاص بالمستخدم المخصوم منه (حركة خصم من الصندوق المصدر)
                Transaction::create([
                    'user_id' => $authUser->id,
                    'cashbox_id' => $fromCashBoxId,
                    'target_user_id' => $toUser->id,
                    'target_cashbox_id' => $toCashBoxId,
                    'created_by' => $authUser->id,
                    'company_id' => $companyId,
                    'type' => 'تحويل',
                    'amount' => -$amount,
                    'balance_before' => $authUserBalance,
                    'balance_after' => $authUserBalance - $amount,
                    'description' => $description,
                    'original_transaction_id' => null,
                ]);

                // إضافة السجل الخاص بالمستخدم المستلم (حركة إضافة إلى الصندوق الهدف)
                // يتم إنشاء سجل منفصل للمستلم حتى لو كان نفس المستخدم لتحقيق تتبع واضح
                $toUserBalance = $toUser->balanceBox($toCashBoxId); // جلب رصيد الصندوق الهدف قبل الإيداع
                Transaction::create([
                    'user_id' => $toUser->id,
                    'cashbox_id' => $toCashBoxId,
                    'target_user_id' => $authUser->id,
                    'target_cashbox_id' => $fromCashBoxId,
                    'created_by' => $authUser->id,
                    'company_id' => $companyId,
                    'type' => 'استلام',
                    'amount' => $amount,
                    'balance_before' => $toUserBalance,
                    'balance_after' => $toUserBalance + $amount,
                    'description' => "استلام من {$authUser->nickname}",
                    'original_transaction_id' => null,
                ]);

                // تحديث أرصدة الخزن (إذا كانت `withdraw` و `deposit` تحدث الرصيد في قاعدة البيانات)
                // تأكد أن هذه الدوال تقوم بتحديث الرصيد الفعلي للصناديق
                // وإلا فسيتم تتبعها فقط في سجلات المعاملات وليس في حقل رصيد مباشر على صندوق
                $authUser->withdraw($amount, $fromCashBoxId); // سحب من الصندوق المصدر
                $toUser->deposit($amount, $toCashBoxId); // إيداع في الصندوق الهدف

                DB::commit();
                // يمكن إرجاع تفاصيل التحويل أو المعاملات الجديدة إذا لزم الأمر
                return api_success([], 'تم تحويل الأموال بنجاح!');
            } catch (Throwable $e) {
                DB::rollback();
                return api_error('فشل التحويل. يرجى المحاولة مرة أخرى.', [], 500);
            }
        } catch (ValidationException $e) {
            return api_error('فشل التحقق من صحة البيانات أثناء تحويل الأموال.', $e->errors(), 422);
        } catch (Throwable $e) {
            return api_exception($e, 500);
        }
    }

    // تم نقل الدالة إلى نموذج المستخدم User كـ ensureCashBoxesForAllCompanies
}

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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException; // تم إضافة هذا الاستيراد
use Throwable; // تم إضافة هذا الاستيراد

// دالة مساعدة لضمان الاتساق في مفاتيح الأذونات (إذا لم تكن معرفة عالميا)
// if (!function_exists('perm_key')) {
//     function perm_key(string $permission): string
//     {
//         return $permission;
//     }
// }

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
    public function index(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();

            if (!$authUser) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required.'
                ], 401);
            }

            $cashBoxQuery = CashBox::query()->with($this->relations);
            $companyId = $authUser->company_id;

            // التحقق الأساسي: إذا لم يكن المستخدم مرتبطًا بشركة وليس سوبر أدمن
            if (!$companyId && !$authUser->hasPermissionTo(perm_key('admin.super'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'User is not associated with a company.'], 403);
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
                } elseif ($authUser->hasAnyPermission([perm_key('cash_boxes.view_any'), perm_key('admin.company')])) {
                    // يرى جميع الصناديق الخاصة بالشركة النشطة (بما في ذلك مديرو الشركة)
                    $cashBoxQuery->whereCompanyIsCurrent();
                } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.view_children'))) {
                    // يرى الصناديق التي أنشأها المستخدم أو المستخدمون التابعون له، ضمن الشركة النشطة
                    $cashBoxQuery->whereCompanyIsCurrent()->whereCreatedByUserOrChildren();
                } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.view_self'))) {
                    // يرى الصناديق التي أنشأها المستخدم فقط، ومرتبطة بالشركة النشطة
                    $cashBoxQuery->whereCompanyIsCurrent()->whereCreatedByUser();
                } else {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view cash boxes.'], 403);
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

            return CashBoxResource::collection($cashBoxes)->additional([
                'total' => $cashBoxes->total(),
                'current_page' => $cashBoxes->currentPage(),
                'last_page' => $cashBoxes->lastPage(),
            ]);
        } catch (Throwable $e) {
            Log::error('CashBox index failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving cash boxes.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * تخزين خزنة جديدة.
     *
     * @param StoreCashBoxRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCashBoxRequest $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // صلاحيات إنشاء صندوق نقدي
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('cash_boxes.create')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You are not authorized to create cash boxes.'
                ], 403);
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
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You can only create cash boxes for your current company unless you are a Super Admin.'], 403);
                }

                $validatedData['created_by'] = $authUser->id;
                // يمكن إضافة 'active' أو حالات افتراضية أخرى هنا إذا كانت موجودة في النموذج
                // $validatedData['active'] = (bool) ($validatedData['active'] ?? true);

                $cashBox = CashBox::create($validatedData);
                $cashBox->load($this->relations);
                DB::commit();
                Log::info('Cash Box created successfully.', ['cash_box_id' => $cashBox->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(new CashBoxResource($cashBox), 201);
            } catch (ValidationException $e) {
                DB::rollback();
                Log::error('CashBox store validation failed: ' . $e->getMessage(), [
                    'errors' => $e->errors(),
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            } catch (Throwable $e) {
                DB::rollback();
                Log::error('CashBox store failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error saving cash box.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('CashBox store failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error saving cash box.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * عرض تفاصيل خزنة معينة.
     *
     * @param CashBox $cashBox
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(CashBox $cashBox)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // التحقق من صلاحيات العرض
            $canView = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canView = true; // المسؤول العام يرى أي صندوق
            } elseif ($authUser->hasAnyPermission([perm_key('cash_boxes.view_any'), perm_key('admin.company')])) {
                // يرى إذا كان الصندوق ينتمي للشركة النشطة (بما في ذلك مديرو الشركة)
                $canView = $cashBox->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.view_children'))) {
                // يرى إذا كان الصندوق أنشأه هو أو أحد التابعين له وتابع للشركة النشطة
                $canView = $cashBox->belongsToCurrentCompany() && $cashBox->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.view_self'))) {
                // يرى إذا كان الصندوق أنشأه هو وتابع للشركة النشطة
                $canView = $cashBox->belongsToCurrentCompany() && $cashBox->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this cash box.'], 403);
            }

            if ($canView) {
                $cashBox->load($this->relations); // تحميل العلاقات
                return response()->json(new CashBoxResource($cashBox));
            }

            return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to view this cash box.'], 403);
        } catch (Throwable $e) {
            Log::error('CashBox show failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'cash_box_id' => $cashBox->id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error retrieving cash box.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * تحديث بيانات خزنة موجودة.
     *
     * @param UpdateCashBoxRequest $request
     * @param CashBox $cashBox
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateCashBoxRequest $request, CashBox $cashBox)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // التحقق من صلاحيات التحديث
            $canUpdate = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canUpdate = true; // المسؤول العام يمكنه تعديل أي صندوق
            } elseif ($authUser->hasAnyPermission([perm_key('cash_boxes.update_any'), perm_key('admin.company')])) {
                // يمكنه تعديل أي صندوق داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canUpdate = $cashBox->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.update_children'))) {
                // يمكنه تعديل الصناديق التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canUpdate = $cashBox->belongsToCurrentCompany() && $cashBox->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.update_self'))) {
                // يمكنه تعديل صندوقه الخاص الذي أنشأه وتابع للشركة النشطة
                $canUpdate = $cashBox->belongsToCurrentCompany() && $cashBox->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this cash box.'], 403);
            }

            if (!$canUpdate) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to update this cash box.'], 403);
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
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You cannot change a cash box\'s company unless you are a Super Admin.'], 403);
                }

                // $validatedData['active'] = (bool) ($validatedData['active'] ?? $cashBox->active); // إذا كان هناك حقل نشط

                $cashBox->update($validatedData);
                $cashBox->load($this->relations);
                DB::commit();
                Log::info('Cash Box updated successfully.', ['cash_box_id' => $cashBox->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(new CashBoxResource($cashBox), 200); // 200 OK for successful update
            } catch (ValidationException $e) {
                DB::rollback();
                Log::error('CashBox update validation failed: ' . $e->getMessage(), [
                    'errors' => $e->errors(),
                    'user_id' => Auth::id(),
                    'cash_box_id' => $cashBox->id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            } catch (Throwable $e) {
                DB::rollback();
                Log::error('CashBox update failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'cash_box_id' => $cashBox->id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error updating cash box.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('CashBox update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'cash_box_id' => $cashBox->id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error updating cash box.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * حذف خزنة.
     *
     * @param CashBox $cashBox
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(CashBox $cashBox)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // التحقق من صلاحيات الحذف
            $canDelete = false;
            if ($authUser->hasPermissionTo(perm_key('admin.super'))) {
                $canDelete = true; // المسؤول العام يمكنه حذف أي صندوق
            } elseif ($authUser->hasAnyPermission([perm_key('cash_boxes.delete_any'), perm_key('admin.company')])) {
                // يمكنه حذف أي صندوق داخل الشركة النشطة (بما في ذلك مديرو الشركة)
                $canDelete = $cashBox->belongsToCurrentCompany();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.delete_children'))) {
                // يمكنه حذف الصناديق التي أنشأها هو أو أحد التابعين له وتابعة للشركة النشطة
                $canDelete = $cashBox->belongsToCurrentCompany() && $cashBox->createdByUserOrChildren();
            } elseif ($authUser->hasPermissionTo(perm_key('cash_boxes.delete_self'))) {
                // يمكنه حذف صندوقه الخاص الذي أنشأه وتابع للشركة النشطة
                $canDelete = $cashBox->belongsToCurrentCompany() && $cashBox->createdByCurrentUser();
            } else {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this cash box.'], 403);
            }

            if (!$canDelete) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You are not authorized to delete this cash box.'], 403);
            }

            DB::beginTransaction();
            try {
                // تحقق مما إذا كان الصندوق مرتبطًا بأي معاملات قبل الحذف
                if (Transaction::where('cashbox_id', $cashBox->id)->exists() || Transaction::where('target_cashbox_id', $cashBox->id)->exists()) {
                    DB::rollback();
                    return response()->json([
                        'error' => 'Conflict',
                        'message' => 'Cannot delete cash box. It contains associated transactions.',
                    ], 409);
                }

                $cashBox->delete();
                DB::commit();
                Log::info('Cash Box deleted successfully.', ['cash_box_id' => $cashBox->id, 'user_id' => $authUser->id, 'company_id' => $companyId]);
                return response()->json(['message' => 'Cash box deleted successfully'], 200);
            } catch (Throwable $e) {
                DB::rollback();
                Log::error('CashBox deletion failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'cash_box_id' => $cashBox->id,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'error' => 'Error deleting cash box.',
                    'details' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (Throwable $e) {
            Log::error('CashBox deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'cash_box_id' => $cashBox->id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error deleting cash box.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * تحويل أموال بين الخزن.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transferFunds(Request $request)
    {
        try {
            /** @var \App\Models\User $authUser */
            $authUser = Auth::user();
            $companyId = $authUser->company_id;

            if (!$authUser || !$companyId) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Authentication or company association required.'], 403);
            }

            // صلاحية خاصة لتحويل الأموال
            if (!$authUser->hasPermissionTo(perm_key('admin.super')) && !$authUser->hasPermissionTo(perm_key('cash_boxes.transfer_funds')) && !$authUser->hasPermissionTo(perm_key('admin.company'))) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'You do not have permission to transfer funds.'], 403);
            }

            $request->validate([
                'to_user_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'cash_box_id' => ['required', 'exists:cash_boxes,id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    // تأكد أن الصندوق ينتمي لشركة المستخدم أو أن المستخدم super_admin
                    $cashBox = CashBox::with(['company', 'creator'])->find($value);
                    if (!$cashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $cashBox->company_id !== $companyId)) {
                        $fail('The selected cash box is invalid or not accessible.');
                    }
                }],
                'to_cash_box_id' => ['required', 'exists:cash_boxes,id', 'different:cash_box_id', function ($attribute, $value, $fail) use ($authUser, $companyId) {
                    // تأكد أن الصندوق الهدف ينتمي لشركة المستخدم أو أن المستخدم super_admin
                    $toCashBox = CashBox::with(['company', 'creator'])->find($value);
                    if (!$toCashBox || (!$authUser->hasPermissionTo(perm_key('admin.super')) && $toCashBox->company_id !== $companyId)) {
                        $fail('The target cash box is invalid or not accessible.');
                    }
                }],
                'description' => 'nullable|string',
            ]);

            $toUser = User::findOrFail($request->to_user_id);
            $amount = $request->amount;
            $fromCashBoxId = $request->cash_box_id; // تم تغيير المتغير ليكون أكثر وضوحًا
            $toCashBoxId = $request->to_cash_box_id; // تم تغيير المتغير ليكون أكثر وضوحًا

            $fromCashBox = CashBox::with(['company'])->findOrFail($fromCashBoxId); // تحميل الشركة هنا للتحقق منها
            $toCashBox = CashBox::with(['company'])->findOrFail($toCashBoxId);

            // التحقق من أن الصناديق ضمن الشركة التي يمكن للمستخدم الوصول إليها (خاصة لغير الـ super_admin)
            if (!$authUser->hasPermissionTo(perm_key('admin.super'))) {
                if (!$fromCashBox->belongsToCurrentCompany() || !$toCashBox->belongsToCurrentCompany()) {
                    return response()->json(['error' => 'Unauthorized', 'message' => 'You can only transfer funds between cash boxes within your company.'], 403);
                }
            }

            // تحقق من رصيد الصندوق المصدر
            $authUserBalance = $authUser->balanceBox($fromCashBoxId);
            if ($authUserBalance < $amount) {
                return response()->json(['error' => 'Insufficient funds', 'message' => 'The source cash box does not have enough balance for this transfer.'], 422);
            }

            // وصف التحويل
            $description = $request->description;
            if (blank($description)) {
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
                    'type' => 'تحويل', // نوع الحركة: تحويل
                    'amount' => -$amount, // قيمة سالبة للدلالة على الخصم
                    'description' => $description,
                    'created_by' => $authUser->id,
                    'company_id' => $companyId,
                    'balance_before' => $authUserBalance,
                    'balance_after' => $authUserBalance - $amount,
                ]);

                // إضافة السجل الخاص بالمستخدم المستلم (حركة إضافة إلى الصندوق الهدف)
                // يتم إنشاء سجل منفصل للمستلم حتى لو كان نفس المستخدم لتحقيق تتبع واضح
                $toUserBalance = $toUser->balanceBox($toCashBoxId); // جلب رصيد الصندوق الهدف قبل الإيداع
                Transaction::create([
                    'user_id' => $toUser->id,
                    'cashbox_id' => $toCashBoxId,
                    'target_cashbox_id' => $fromCashBoxId,
                    'target_user_id' => $authUser->id,
                    'type' => 'استلام', // نوع الحركة: استلام
                    'amount' => $amount, // قيمة موجبة للدلالة على الإضافة
                    'description' => "استلام من {$authUser->nickname}",
                    'created_by' => $authUser->id,
                    'company_id' => $companyId,
                    'balance_before' => $toUserBalance,
                    'balance_after' => $toUserBalance + $amount,
                ]);

                // تحديث أرصدة الخزن (إذا كانت `withdraw` و `deposit` تحدث الرصيد في قاعدة البيانات)
                // تأكد أن هذه الدوال تقوم بتحديث الرصيد الفعلي للصناديق
                // وإلا فسيتم تتبعها فقط في سجلات المعاملات وليس في حقل رصيد مباشر على صندوق
                $authUser->withdraw($amount, $fromCashBoxId); // سحب من الصندوق المصدر
                $toUser->deposit($amount, $toCashBoxId); // إيداع في الصندوق الهدف

                DB::commit();
                Log::info('Funds transferred successfully.', [
                    'from_cash_box' => $fromCashBoxId,
                    'to_cash_box' => $toCashBoxId,
                    'amount' => $amount,
                    'user_id' => $authUser->id,
                    'company_id' => $companyId,
                ]);

                return response()->json(['message' => 'Funds transferred successfully!'], 200);
            } catch (Throwable $e) {
                DB::rollback();
                Log::error('Funds transfer failed in transaction: ' . $e->getMessage(), [
                    'exception' => $e,
                    'user_id' => Auth::id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'message' => 'Transfer failed. Please try again.',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (ValidationException $e) {
            Log::error('Funds transfer validation failed: ' . $e->getMessage(), [
                'errors' => $e->errors(),
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed during fund transfer.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Funds transfer failed: ' . $e->getMessage(), [
                'exception' => $e,
                'user_id' => Auth::id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Transfer failed. An unexpected error occurred.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}

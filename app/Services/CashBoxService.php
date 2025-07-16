<?php

namespace App\Services;

use App\Models\User;
use App\Models\CashBox;
use App\Models\CashBoxType;
use Illuminate\Support\Facades\Auth;
use Throwable; // لاستخدامها في معالجة الأخطاء المحتملة

class CashBoxService
{
    /**
     * تضمن وجود خزنة نقدية افتراضية للمستخدم في شركته الحالية.
     *
     * @param \App\Models\User $user المستخدم
     * @param int|null $createdById معرف من قام بالإنشاء (اختياري)
     * @return \App\Models\CashBox|null الخزنة التي تم العثور عليها أو إنشاؤها، أو null في حالة الفشل.
     */
    public function ensureCashBoxForUser(User $user, int|null $createdById = null): ?CashBox
    {
        // البحث عن نوع "نقدي" للخزنة
        $cashType = CashBoxType::where('name', 'نقدي')->first();

        // إذا لم يتم العثور على نوع "نقدي"، لا يمكن إنشاء الخزنة
        if (!$cashType) {
            // يمكن هنا تسجيل خطأ أو إلقاء استثناء إذا كان هذا النوع ضروريًا دائمًا
            return null;
        }

        try {
            // استخدام firstOrCreate لضمان عدم التكرار والاستفادة من القيد الفريد
            // تبحث عن خزنة مطابقة للمعايير، وإذا لم تجدها، تقوم بإنشائها
            return CashBox::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                    'cash_box_type_id' => $cashType->id,
                    'is_default' => true, // هذا حاسم للقيد الفريد
                ],
                [
                    'name' => 'الخزنة النقدية',
                    'balance' => 0,
                    'created_by' => $createdById
                        ?? Auth::id()
                        ?? $user->created_by
                        ?? $user->id,
                    'description' => 'تم إنشاؤها تلقائيًا مع المستخدم',
                    'account_number' => null,
                ]
            );
        } catch (Throwable $e) {
            // في حالة وجود خطأ (مثل انتهاك القيد الفريد في ظروف سباق نادرة جداً)
            // يمكن هنا تسجيل الخطأ للمراجعة
            // Log::error("فشل في إنشاء صندوق الكاش الافتراضي للمستخدم {$user->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تضمن وجود خزنة نقدية افتراضية لكل شركة جديدة رُبط بها المستخدم.
     *
     * @param \App\Models\User $user المستخدم
     * @param array $companyIds مصفوفة بمعرفات الشركات
     * @param int|null $createdById معرف من قام بالإنشاء (اختياري)
     * @return void
     */
    public function ensureCashBoxesForUserCompanies(User $user, array $companyIds, int|null $createdById = null): void
    {
        // البحث عن نوع "نقدي" للخزنة
        $cashType = CashBoxType::where('name', 'نقدي')->first();
        if (!$cashType) {
            // يمكن هنا تسجيل خطأ
            return;
        }

        foreach ($companyIds as $companyId) {
            try {
                // استخدام firstOrCreate لضمان عدم التكرار لكل شركة
                CashBox::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'company_id' => $companyId,
                        'cash_box_type_id' => $cashType->id,
                        'is_default' => true,
                    ],
                    [
                        'name' => 'الخزنة النقدية',
                        'balance' => 0,
                        'created_by' => $createdById
                            ?? Auth::id()
                            ?? $user->created_by
                            ?? $user->id,
                        'description' => 'تم إنشاؤها تلقائيًا مع ربط المستخدم بالشركة',
                        'account_number' => null,
                    ]
                );
            } catch (Throwable $e) {
                // في حالة وجود خطأ لكل شركة (مثل انتهاك القيد الفريد)
                // Log::error("فشل في إنشاء صندوق الكاش الافتراضي للمستخدم {$user->id} والشركة {$companyId}: " . $e->getMessage());
                continue; // الاستمرار في الشركات الأخرى حتى لو فشل إنشاء واحدة
            }
        }
    }
}

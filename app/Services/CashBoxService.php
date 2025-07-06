<?php

namespace App\Services;

use App\Models\User;
use App\Models\CashBox;
use App\Models\CashBoxType;
use Illuminate\Support\Facades\Auth;

class CashBoxService
{
    /**
     * إنشاء خزنة نقدية للمستخدم إذا لم تكن موجودة في شركته الحالية.
     */
    public function ensureCashBoxForUser(User $user, int|null $createdById = null): ?CashBox
    {
        $cashType = CashBoxType::where('name', 'نقدي')->first();

        if (!$cashType) {
            return null;
        }

        $hasCashBox = $user->cashBoxes()
            ->where('cash_box_type_id', $cashType->id)
            ->where('company_id', $user->company_id)
            ->exists();

        if (!$hasCashBox) {
            return CashBox::create([
                'name' => 'الخزنة النقدية',
                'balance' => 0,
                'cash_box_type_id' => $cashType->id,
                'is_default' => true,
                'user_id' => $user->id,
                'created_by' => $createdById
                    ?? Auth::id()
                    ?? $user->created_by
                    ?? $user->id,
                'company_id' => $user->company_id,
                'description' => 'تم إنشاؤها تلقائيًا مع المستخدم',
                'account_number' => null,
            ]);
        }

        return null;
    }

    /**
     * إنشاء خزنة لكل شركة جديدة رُبط بها المستخدم.
     */
    public function ensureCashBoxesForUserCompanies(User $user, array $companyIds, int|null $createdById = null): void
    {
        $cashType = CashBoxType::where('name', 'نقدي')->first();
        if (!$cashType) return;

        foreach ($companyIds as $companyId) {
            $hasCashBox = $user->cashBoxes()
                ->where('cash_box_type_id', $cashType->id)
                ->where('company_id', $companyId)
                ->exists();

            if (!$hasCashBox) {
                CashBox::create([
                    'name' => 'الخزنة النقدية',
                    'balance' => 0,
                    'cash_box_type_id' => $cashType->id,
                    'is_default' => true,
                    'user_id' => $user->id,
                    'created_by' => $createdById
                        ?? Auth::id()
                        ?? $user->created_by
                        ?? $user->id,
                    'company_id' => $companyId,
                    'description' => 'تم إنشاؤها تلقائيًا مع ربط المستخدم بالشركة',
                    'account_number' => null,
                ]);
            }
        }
    }
}

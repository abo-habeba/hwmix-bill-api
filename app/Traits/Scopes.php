<?php

namespace App\Traits;

use App\Models\User;
use App\Scopes\CompanyScope; // استيراد الـ Global Scope
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait Scopes
{
    /**
     * يتم استدعاء هذه الدالة تلقائيًا بواسطة Laravel عند استخدام الـ Trait في النموذج.
     * هنا نقوم بتطبيق Global Scope.
     *
     * @return void
     */
    protected static function bootScopes()
    {
        static::addGlobalScope(new CompanyScope);
    }

    /**
     * نطاق لجلب السجلات التي أنشأها المستخدم الحالي أو المستخدمون التابعون له.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereCreatedByUserOrChildren(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user) {
            $descendantUserIds = $user->getDescendantUserIds();
            $descendantUserIds[] = $user->id;
            return $query->whereIn('created_by', $descendantUserIds);
        }

        return $query->whereRaw('0 = 1');
    }

    /**
     * نطاق لجلب السجلات التي أنشأها المستخدم الحالي فقط.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereCreatedByUser(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user) {
            return $query->where('created_by', $user->id);
        }

        return $query->whereRaw('0 = 1');
    }

    /**
     * التحقق مما إذا كان الكائن ينتمي إلى الشركة النشطة للمستخدم الحالي.
     *
     * @return bool
     */
    public function belongsToCurrentCompany(): bool
    {
        $user = Auth::user();
        return $user && $this->company_id && $this->company_id === $user->company_id;
    }

    /**
     * التحقق مما إذا كان الكائن قد أنشأه المستخدم الحالي.
     *
     * @return bool
     */
    public function createdByCurrentUser(): bool
    {
        $user = Auth::user();
        return $user && $this->created_by === $user->id;
    }

    /**
     * التحقق مما إذا كان الكائن قد أنشأه المستخدم الحالي أو أحد المستخدمين التابعين له.
     *
     * @return bool
     */
    public function createdByUserOrChildren(): bool
    {
        $user = Auth::user();
        if (!$user || !$this->created_by) {
            return false;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        $descendantUserIds = $user->getDescendantUserIds();
        return in_array($this->created_by, $descendantUserIds);
    }
}

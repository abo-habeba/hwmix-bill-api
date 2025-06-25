<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait Scopes
{
    /**
     * نطاق لجلب السجلات المرتبطة بشركة المستخدم الحالي (المالك/المدير) فقط.
     * يفترض أن المستخدم الذي يستخدم هذا النطاق هو "مالك" أو "مدير" للشركة.
     * لا يتضمن السجلات التي company_id لها null.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereCompanyIsCurrent(Builder $query): Builder
    {
        $user = Auth::user();

        if (!$user || !$user->company_id) {
            return $query->whereRaw('0 = 1');  // إرجاع استعلام لا يعيد أي نتائج
        }

        return $query->where('company_id', $user->company_id);
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
            $subUsers = User::where('created_by', $user->id)->pluck('id')->toArray();
            $subUsers[] = $user->id;  // أضف المستخدم نفسه إلى قائمة المستخدمين التابعين
            return $query->whereIn('created_by', $subUsers);
        }

        return $query->whereRaw('0 = 1');  // لا ترجع شيئًا إذا لم يكن هناك مستخدم
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

        return $query->whereRaw('0 = 1');  // لا ترجع شيئًا إذا لم يكن هناك مستخدم
    }

    public function belongsToCurrentCompany(): bool
    {
        $user = Auth::user();
        return $user && $this->company_id && $this->company_id === $user->company_id;
    }

    public function createdByCurrentUser(): bool
    {
        $user = Auth::user();
        return $user && $this->created_by === $user->id;
    }

    public function createdByUserOrChildren(): bool
    {
        $user = Auth::user();
        if (!$user || !$this->created_by)
            return false;

        if ($this->created_by === $user->id) {
            return true;
        }

        return User::where('created_by', $user->id)->where('id', $this->created_by)->exists();
    }
}

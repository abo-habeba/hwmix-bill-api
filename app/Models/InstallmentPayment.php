<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperInstallmentPayment
 */
class InstallmentPayment extends Model
{
    use HasFactory, Scopes, Blameable;

    protected $fillable = [
        'installment_plan_id',
        'company_id',
        'created_by',
        'payment_date',
        'amount_paid',
        'payment_method',
        'notes',
    ];

    public function plan()
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details()
    {
        return $this->hasMany(InstallmentPaymentDetail::class, 'installment_payment_id');
    }

    public function installments()
    {
        return $this->belongsToMany(Installment::class, 'installment_payment_details')
            ->withPivot('amount_paid')
            ->withTimestamps();
    }
}

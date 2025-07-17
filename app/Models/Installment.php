<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperInstallment
 */
class Installment extends Model
{
    use HasFactory, LogsActivity, Blameable, Scopes, SoftDeletes;

    protected $fillable = [
        'installment_plan_id',
        'installment_number',
        'due_date',
        'amount',
        'status',
        'paid_at',
        'remaining',
        'created_by',
        'user_id',
        'company_id',
    ];

    protected $casts = [
        'due_date' => 'datetime', // أضف هذا السطر
        'paid_at' => 'datetime',  // أضف هذا السطر
    ];

    // العلاقات
    public function installmentPlan()
    {
        return $this->belongsTo(InstallmentPlan::class);
    }

    public function payments()
    {
        return $this->belongsToMany(InstallmentPayment::class, 'installment_payment_details')
            ->withPivot('amount_paid')
            ->withTimestamps();
    }

    public function paymentDetails()
    {
        return $this->hasMany(InstallmentPaymentDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

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
        'payment_date',
        'amount_paid',
        'payment_method',
        'notes'
    ];
    public function plan()
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }
}

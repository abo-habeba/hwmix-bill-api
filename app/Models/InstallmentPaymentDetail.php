<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentPaymentDetail extends Model
{
    protected $table = 'installment_payment_details';
    protected $guarded = [];

    public function installmentPayment()
    {
        return $this->belongsTo(InstallmentPayment::class);
    }

    public function installment()
    {
        return $this->belongsTo(Installment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InstallmentPaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'installment_payment_id',
        'installment_id',
        'amount_paid',
    ];

    public function installmentPayment()
    {
        return $this->belongsTo(InstallmentPayment::class);
    }

    public function installment()
    {
        return $this->belongsTo(Installment::class);
    }
}

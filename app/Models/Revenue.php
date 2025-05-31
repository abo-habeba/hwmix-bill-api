<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Revenue extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'customer_id',
        'created_by',
        'wallet_id',
        'company_id',
        'amount',
        'paid_amount',
        'remaining_amount',
        'payment_method',
        'note',
        'revenue_date',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function wallet()
    {
        return $this->belongsTo(CashBox::class, 'wallet_id');
    }
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}

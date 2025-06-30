<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperInstallmentPlan
 */
class InstallmentPlan extends Model
{
    use HasFactory,
        Blameable,
        Scopes;  // Assuming you have a Blameable trait for tracking created_by

    protected $fillable = [
        'invoice_id',
        'user_id',
        'total_amount',
        'down_payment',
        'remaining_amount',
        'company_id',
        'created_by',
        'number_of_installments',
        'installment_amount',
        'start_date',
        'end_date',
        'status',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(InstallmentPayment::class);
    }

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

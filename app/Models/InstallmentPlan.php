<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes; // ← ✅ استيراد السوفت دليت

/**
 * @mixin IdeHelperInstallmentPlan
 */
class InstallmentPlan extends Model
{
    use HasFactory, Blameable, Scopes, SoftDeletes; // ← ✅ استخدم السوفت دليت

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
        'notes',
        'round_step', // ← ضفناها هنا كمان
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    // العلاقات زي ما هي 👇
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function installments()
    {
        return $this->hasMany(Installment::class);
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
    public function payments()
    {
        return $this->hasMany(InstallmentPayment::class);
    }
}

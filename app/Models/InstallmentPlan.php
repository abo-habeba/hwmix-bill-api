<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstallmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'user_id', 'total_amount', 'down_payment', 'remaining_amount', 'company_id', 'created_by',
        'number_of_installments', 'installment_amount', 'start_date', 'end_date', 'status', 'notes'
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

    public function schedules()
    {
        return $this->hasMany(InstallmentPlanSchedule::class);
    }
}

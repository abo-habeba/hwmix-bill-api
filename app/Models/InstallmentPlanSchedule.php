<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstallmentPlanSchedule extends Model
{
    use HasFactory;
    protected $fillable = [
        'installment_plan_id', 'due_date', 'installment_amount', 'status', 'paid_date'
    ];
    public function plan() { return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id'); }
}

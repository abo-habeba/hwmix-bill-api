<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    use HasFactory;
    protected $fillable = [
        'installment_plan_id', 'due_date', 'amount', 'status', 'paid_at', 'remaining'
    ];
    public function installmentPlan()
    {
        return $this->belongsTo(InstallmentPlan::class);
    }
    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'payment_installment')
            ->withPivot('allocated_amount')->withTimestamps();
    }
}

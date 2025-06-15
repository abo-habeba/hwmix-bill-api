<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    use HasFactory,
        \App\Traits\Blameable;  // Assuming you have a Blameable trait for tracking created_by

    protected $fillable = [
        'installment_plan_id',
        'installment_number',
        'due_date',
        'amount',
        'status',
        'paid_at',
        'remaining',
        'created_by',
        'user_id'
    ];

    public function installmentPlan()
    {
        return $this->belongsTo(InstallmentPlan::class);
    }

    // public function payments()
    // {
    //     return $this->belongsToMany(Payment::class, 'payment_installment')
    //         ->withPivot('allocated_amount')->withTimestamps();
    // }

    // القسط يخص عميل
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // القسط أضافه موظف
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

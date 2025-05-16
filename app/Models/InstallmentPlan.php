<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstallmentPlan extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'invoice_id', 'total_amount', 'down_payment', 'installment_count',
        'installment_amount', 'start_date', 'due_day', 'notes'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function installments()
    {
        return $this->hasMany(Installment::class);
    }
}

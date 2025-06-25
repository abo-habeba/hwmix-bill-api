<?php
namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperPayment
 */
class Payment extends Model
{
    use HasFactory,Blameable;
    protected $fillable = [
        'user_id', 'payment_date', 'amount', 'method', 'notes', 'is_split'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function installments()
    {
        return $this->belongsToMany(Installment::class, 'payment_installment')
            ->withPivot('allocated_amount')->withTimestamps();
    }
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}

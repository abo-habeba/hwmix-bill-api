<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperPaymentMethod
 */
class PaymentMethod extends Model
{
    use HasFactory, Scopes, Blameable;
    protected $fillable = ['name', 'code', 'active'];
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}

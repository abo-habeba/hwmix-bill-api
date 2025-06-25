<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperService
 */
class Service extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'description', 'default_price'
    ];
    public function subscriptions() { return $this->hasMany(Subscription::class); }
}

<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperProfit
 */
class Profit extends Model
{
    use Scopes, Blameable;
    protected $fillable = [
        'source_type',
        'source_id',
        'created_by',
        'user_id',
        'company_id',
        'revenue_amount',
        'cost_amount',
        'profit_amount',
        'note',
        'profit_date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}

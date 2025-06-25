<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperProfit
 */
class Profit extends Model
{
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

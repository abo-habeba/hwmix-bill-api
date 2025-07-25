<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperQuotation
 */
class Quotation extends Model
{
    use Scopes, Blameable;
    protected $fillable = [
        'invoice_number',
        'total_amount',
        'status',
        'company_id',
        'created_by',
    ];
}

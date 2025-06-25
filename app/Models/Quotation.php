<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperQuotation
 */
class Quotation extends Model
{
    protected $fillable = [
        'invoice_number',
        'total_amount',
        'status',
        'company_id',
        'created_by',
    ];
}

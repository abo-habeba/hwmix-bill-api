<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperInvoiceType
 */
class InvoiceType extends Model
{
    use HasFactory, Scopes, Blameable;
    protected $fillable = [
        'name',
        'description',
        'code',
        'context',
        'company_id',
        'created_by'
    ];
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}

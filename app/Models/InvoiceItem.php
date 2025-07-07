<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\Blameable;
use App\Traits\Scopes;

/**
 * @mixin IdeHelperInvoiceItem
 */
class InvoiceItem extends Model
{
    use HasFactory, Blameable, Scopes;

    protected $guarded = [];
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

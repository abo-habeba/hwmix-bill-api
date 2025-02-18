<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = ['warehouse_id', 'product_variant_id', 'quantity'];

    public function warehouse() {
        return $this->belongsTo(Warehouse::class);
    }

    public function productVariant() {
        return $this->belongsTo(ProductVariant::class);
    }
}

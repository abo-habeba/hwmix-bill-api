<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'quantity',
        'reserved_quantity',
        'expiry_date',
        'status',
        'batch_number',
        'unit_cost',
        'location',
        'warehouse_id',
        'product_variant_id',
        'company_id',
        'created_by',
        'updated_by',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}

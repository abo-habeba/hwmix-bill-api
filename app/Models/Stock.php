<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperStock
 */
class Stock extends Model
{
    use HasFactory, Blameable;

    protected $fillable = [
        'quantity', 'reserved', 'min_quantity', 'cost',
        'batch', 'expiry', 'loc', 'status',
        'variant_id', 'warehouse_id', 'company_id',
        'created_by', 'updated_by'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved' => 'integer',
        'min_quantity' => 'integer',
        'cost' => 'decimal:2',
        'expiry' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($stock) {
            if (empty($stock->batch)) {
                $stock->batch = 'B-' . now()->format('Ymd') . '-' . rand(1000, 9999);
            }
        });
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

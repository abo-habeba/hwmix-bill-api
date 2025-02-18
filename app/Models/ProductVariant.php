<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'price', 'stock'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function attributes()
    {
        return $this->hasMany(ProductVariantAttribute::class);
    }
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($variant) {
            $variant->sku = self::generateUniqueSKU();
            $variant->barcode = self::generateUniqueBarcode();
        });
    }

    private static function generateUniqueSKU()
    {
        do {
            $sku = 'SKU-' . strtoupper(Str::random(8));
        } while (self::where('sku', $sku)->exists());

        return $sku;
    }

    private static function generateUniqueBarcode()
    {
        do {
            $barcode = 'BC' . rand(1000000000, 9999999999); // 10 digits barcode
        } while (self::where('barcode', $barcode)->exists());

        return $barcode;
    }
}


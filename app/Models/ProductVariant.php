<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode',
        'sku',
        'purchase_price',
        'wholesale_price',
        'retail_price',
        'stock_threshold',
        'status',
        'expiry_date',
        'image_url',
        'weight',
        'dimensions',
        'tax_rate',
        'discount',
        'product_id',
        'warehouse_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stock()
    {
        return $this->hasOne(Stock::class, 'product_variant_id');
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
        // الحصول على آخر قيمة للباركود من قاعدة البيانات
        $lastBarcode = self::orderBy('barcode', 'desc')->first();

        // إذا لم يكن هناك باركودات سابقة، ابدأ من الرقم 1000000000
        $nextBarcode = $lastBarcode ? $lastBarcode->barcode + 1 : 1000000000;

        // ملء الأصفار لتأكيد أن الباركود طويل بما يكفي (على سبيل المثال 10 خانات)
        $barcode = str_pad($nextBarcode, 10, '0', STR_PAD_LEFT);

        // التأكد من أن الباركود فريد
        while (self::where('barcode', $barcode)->exists()) {
            $nextBarcode++;
            $barcode = str_pad($nextBarcode, 10, '0', STR_PAD_LEFT);
        }

        return $barcode;
    }

}

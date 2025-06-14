<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use HasFactory, Blameable;

    protected $fillable = [
        'barcode',
        'sku',
        'retail_price',
        'wholesale_price',
        'image',
        'weight',
        'dimensions',
        'tax',
        'discount',
        'status',
        'product_id'
    ];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'dimensions' => 'array',  // Assuming dimensions is stored as an array
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'variant_id');
    }

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

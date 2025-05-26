<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'category_id',
        'created_by',
        'warehouse_id',
        'brand_id',
        'name',
        'description',
        'price',
        'slug',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    // علاقة المنتج مع الـ variants
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // علاقة المنتج مع المخزون (stock)
    // public function stock()
    // {
    //     return $this->hasOne(Stock::class); // يمكن تعديلها إذا كان منتج واحد يمكن أن يكون له عدة مخزونات
    // }


    // علاقة المنتج مع الصور
    // public function images()
    // {
    //     return $this->hasMany(ProductImage::class); // إذا كان هناك صور متعددة
    // }

    // علاقة المنتج مع الطلبات (order_items) إذا كنت تستخدم هذا النموذج
    // public function orderItems()
    // {
    //     return $this->hasMany(OrderItem::class);
    // }

    // إذا كان لديك خاصية خصومات للمنتجات
    // public function discount()
    // {
    //     return $this->hasOne(ProductDiscount::class); // إذا كان منتج واحد يحتوي على خصم واحد
    // }

    // دالة لتوليد الـ slug
    public static function generateSlug($name)
{
    $slug = preg_replace('/[^\p{Arabic}a-z0-9\s-]/u', '', strtolower($name));
    $slug = preg_replace('/\s+/', '-', trim($slug));
    $slug = preg_replace('/-+/', '-', $slug);

    // في حال كانت النتيجة فارغة
    if (empty($slug)) {
        $slug = 'منتج'; // أو 'product' إن كنت تفضل الإنجليزي
    }

    $originalSlug = $slug;
    $i = 1;
    // التأكد من uniqueness
    while (self::where('slug', $slug)->exists()) {
        $slug = $originalSlug . '-' . $i;
        $i++;
    }

    return $slug;
}

}

<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperProduct
 */
class Product extends Model
{
    use HasFactory, Blameable;

    protected $fillable = [
        'name',
        'slug',
        'active',
        'featured',
        'returnable',
        'desc',
        'desc_long',
        'published_at',
        'category_id',
        'brand_id',
        'company_id',
        'created_by'
    ];

    protected $casts = [
        'active' => 'boolean',
        'featured' => 'boolean',
        'returnable' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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

    // // علاقة المنتج مع الصور
    // public function images()
    // {
    //     return $this->hasMany(ProductImage::class);  // إذا كان هناك صور متعددة
    // }

    // دالة لتوليد الـ slug
    public static function generateSlug($name)
    {
        $slug = preg_replace('/[^\p{Arabic}a-z0-9\s-]/u', '', strtolower($name));
        $slug = preg_replace('/\s+/', '-', trim($slug));
        $slug = preg_replace('/-+/', '-', $slug);

        // في حال كانت النتيجة فارغة
        if (empty($slug)) {
            $slug = 'منتج';  // أو 'product' إن كنت تفضل الإنجليزي
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
};

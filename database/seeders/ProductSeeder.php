<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // بيانات فيك للمنتجات (2 منتجات)
        for ($i = 0; $i < 2; $i++) {
            Product::create([
                'name' => 'منتج ' . ($i + 1),
                'slug' => 'product-' . ($i + 1),
                'is_active' => true,
                'featured' => false,
                'is_returnable' => true,
                // 'meta_data' => json_encode(['info' => 'بيانات تجريبية']),
                'published_at' => now(),
                'description' => 'وصف المنتج ' . ($i + 1),
                'description_long' => 'تفاصيل المنتج ' . ($i + 1),
                'company_id' => 1,
                'category_id' => 1,
                'brand_id' => 1,
                'warehouse_id' => 1,
                'created_by' => 1,
            ]);
        }
    }
}

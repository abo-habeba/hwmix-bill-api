<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $product = Product::create([
                'name' => 'منتج ' . ($i + 1),
                'slug' => 'product-' . ($i + 1),
                'active' => true,
                'featured' => false,
                'returnable' => true,
                'published_at' => now(),
                'desc' => 'وصف المنتج ' . ($i + 1),
                'desc_long' => 'تفاصيل المنتج ' . ($i + 1),
                'company_id' => 1,
                'category_id' => 1,
                'brand_id' => 1,
                'created_by' => 1,
            ]);

            // إضافة متغير (Variant)
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'barcode' => 'BR-' . rand(100000, 999999),
                'sku' => 'SKU-' . rand(1000, 9999),
                'wholesale_price' => 60,
                'retail_price' => 75,
                'profit_margin' => 0.25,  // هامش الربح تمت إضافته
                'image' => null,  // تم تغيير 'image_url' إلى 'image'
                'weight' => 1.5,
                'dimensions' => '20x30x10',
                'tax' => 5,  // تم تغيير 'tax_rate' إلى 'tax'
                'discount' => 0,
                'status' => 'active',
                'company_id' => 1,
                'created_by' => 1,
            ]);

            // إضافة مخزون للمتغير
            Stock::create([
                'variant_id' => $variant->id,
                'warehouse_id' => 1,
                'company_id' => 1,
                'created_by' => 1,
                'quantity' => 100,
                'reserved' => 5,
                'min_quantity' => 10,
                'cost' => 50,  // سعر الشراء للوحدة
                'batch' => 'BATCH-' . strtoupper(uniqid()),
                'expiry' => now()->addMonths(6),
                'loc' => 'رف-أ',
                'status' => 'available',
                'updated_by' => null,  // تمت إضافته كاختياري
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Product;
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
                'is_active' => true,
                'featured' => false,
                'is_returnable' => true,
                'published_at' => now(),
                'description' => 'وصف المنتج ' . ($i + 1),
                'description_long' => 'تفاصيل المنتج ' . ($i + 1),
                'company_id' => 1,
                'category_id' => 1,
                'brand_id' => 1,
                'warehouse_id' => 1,
                'created_by' => 1,
            ]);

            // $variant = $product->variants()->create([
            //     'barcode' => '100000000' . ($i + 1),
            //     'sku' => 'SKU' . ($i + 1),
            //     'purchase_price' => 100 + ($i * 10),
            //     'wholesale_price' => 120 + ($i * 10),
            //     'retail_price' => 150 + ($i * 10),
            //     // 'stock_threshold' => 10,
            //     'status' => 'active',
            //     'expiry_date' => null,
            //     'image_url' => null,
            //     'weight' => 1.5,
            //     'dimensions' => '10x20x5',
            //     'tax_rate' => 5.0,
            //     'discount' => 0,
            //     'warehouse_id' => 1,
            // ]);

            // // أضف مخزون للمتغير
            // Stock::create([
            //     'product_variant_id' => $variant->id,
            //     'warehouse_id' => 1,
            //     'quantity' => 100,
            //     'company_id' => 1,  // ✅ ضروري لتفادي الخطأ
            //     'created_by' => 1,  // ✅ ضروري لتفادي الخطأ
            // ]);
        }
    }
}

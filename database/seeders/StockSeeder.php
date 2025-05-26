<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stock;
use App\Models\ProductVariant;
use App\Models\Warehouse;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        $variants = ProductVariant::all();
        $warehouse = Warehouse::first();
        foreach ($variants as $variant) {
            Stock::create([
                'warehouse_id' => $warehouse->id,
                'product_variant_id' => $variant->id,
                'quantity' => 100,
            ]);
        }
    }
}

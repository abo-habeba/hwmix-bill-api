<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\WarehouseSeeder;

// تمت عملية الدمج في DatabaseSeeder. يمكنك حذف هذا الملف إذا لم تعد بحاجة له.
// تمت إزالة InvoiceItemSeeder من هنا لتفادي التكرار، حيث أنه موجود بالفعل في DatabaseSeeder.

class DatabaseFullSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // استدعاء جميع Seeders الخاصة بالجداول هنا
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            CompanySeeder::class,
            WarehouseSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
            InvoiceTypeSeeder::class,
            AttributeSeeder::class,
            AttributeValueSeeder::class,
            StockSeeder::class,
            RevenueSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
    }
}

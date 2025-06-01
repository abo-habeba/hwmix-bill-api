<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

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
            InvoiceItemSeeder::class,
            RevenueSeeder::class,
            // InvoiceTypeSeeder::class, // مكرر، تم حذفه
        ]);
    }
}

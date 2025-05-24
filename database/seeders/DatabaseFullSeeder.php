<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

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
            CategorySeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
            // ... أضف seeders أخرى إذا لزم
        ]);
    }
}

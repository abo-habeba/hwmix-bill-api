<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warehouse;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::create([
            'name' => 'المخزن الرئيسي',
            'location' => 'الموقع الرئيسي',
            'manager' => 'مدير المخزن',
            'capacity' => 1000,
            'status' => 'active',
            'company_id' => 1,
            'created_by' => 1,
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        // بيانات فيك للماركات (ماركتين فقط)
        for ($i = 0; $i < 2; $i++) {
            Brand::create([
                'name' => 'ماركة ' . ($i + 1),
                'description' => 'وصف الماركة ' . ($i + 1),
                'company_id' => 1,
                'created_by' => 1,
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        // بيانات فيك للماركات (ماركتين فقط)
            Brand::create([
                'name' => 'الطوخي',
                'description' => ' ماركة الطوخي للملابس الجاهزة',
                'company_id' => 1,
                'created_by' => 1,
            ]);
            Brand::create([
                'name' => 'الدهان',
                'description' => ' ماركة الدهان  للادوات المنزلية',
                'company_id' => 1,
                'created_by' => 1,
            ]);
            Brand::create([
                'name' => 'فاتيكا',
                'description' => ' ماركة فاتيكا للعناية بالشعر',
                'company_id' => 1,
                'created_by' => 1,
            ]);
            Brand::create([
                'name' => 'بيتي',
                'description' => ' ماركة بيتي للادوات المنزلية',
                'company_id' => 1,
                'created_by' => 1,
            ]);
    }
}

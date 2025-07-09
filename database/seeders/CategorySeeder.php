<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // بيانات فيك للأقسام (2 أقسام)
        Category::create([
            'name' => ' الملابس الجاهزة',
            'description' => ' قسم الملابس الجاهزة',
            'company_id' => 1,
            'created_by' => 1,
        ]);
    }
}

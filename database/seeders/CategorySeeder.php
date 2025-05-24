<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // بيانات فيك للأقسام (2 أقسام)
        for ($i = 0; $i < 2; $i++) {
            Category::create([
                'name' => 'قسم ' . ($i + 1),
                'description' => 'وصف القسم ' . ($i + 1),
                'company_id' => 1,
                'created_by' => 1,
            ]);
        }
    }
}

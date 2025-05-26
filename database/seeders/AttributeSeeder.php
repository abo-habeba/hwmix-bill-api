<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attribute;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        // إنشاء 2 خاصية مرتبطة بالشركة والمستخدم الأول
        for ($i = 0; $i < 2; $i++) {
            Attribute::create([
                'name' => 'خاصية ' . ($i + 1),
                'company_id' => 1,
                'created_by' => 1,
            ]);
        }
    }
}

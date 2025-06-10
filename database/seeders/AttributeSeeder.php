<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            [
                'id' => 1,
                'name' => 'لون',
                'value' => null,
                'company_id' => 1,
                'created_by' => 1,
                'created_at' => '2025-06-09 23:43:56',
                'updated_at' => '2025-06-09 23:43:56'
            ],
            [
                'id' => 2,
                'name' => 'الحجم',
                'value' => null,
                'company_id' => 1,
                'created_by' => 1,
                'created_at' => '2025-06-09 23:45:22',
                'updated_at' => '2025-06-09 23:45:22'
            ]
        ];

        foreach ($attributes as $attributeData) {
            Attribute::create($attributeData);
        }
    }
}

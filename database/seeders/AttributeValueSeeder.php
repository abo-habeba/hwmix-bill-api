<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Seeder;

class AttributeValueSeeder extends Seeder
{
    public function run(): void
    {
        $attributeValues = [
            [
                'attribute_id' => 1,
                'created_by' => 1,
                'name' => 'احمر',
                'color' => '#F44336',
                'created_at' => '2025-06-09 23:44:08',
                'updated_at' => '2025-06-09 23:44:08'
            ],
            [
                'attribute_id' => 1,
                'created_by' => 1,
                'name' => 'ازرق',
                'color' => '#2196F3',
                'created_at' => '2025-06-09 23:44:38',
                'updated_at' => '2025-06-09 23:44:38'
            ],
            [
                'attribute_id' => 1,
                'created_by' => 1,
                'name' => 'اسود',
                'color' => '#000000',
                'created_at' => '2025-06-09 23:44:51',
                'updated_at' => '2025-06-09 23:44:51'
            ],
            [
                'attribute_id' => 2,
                'created_by' => 1,
                'name' => 'XL',
                'color' => null,
                'created_at' => '2025-06-09 23:45:30',
                'updated_at' => '2025-06-09 23:45:30'
            ],
            [
                'attribute_id' => 2,
                'created_by' => 1,
                'name' => '3XL',
                'color' => null,
                'created_at' => '2025-06-09 23:45:37',
                'updated_at' => '2025-06-09 23:46:29'
            ],
            [
                'attribute_id' => 2,
                'created_by' => 1,
                'name' => 'كبير',
                'color' => null,
                'created_at' => '2025-06-09 23:45:44',
                'updated_at' => '2025-06-09 23:45:44'
            ],
            [
                'attribute_id' => 2,
                'created_by' => 1,
                'name' => 'صغير',
                'color' => null,
                'created_at' => '2025-06-09 23:45:50',
                'updated_at' => '2025-06-09 23:45:50'
            ],
            [
                'attribute_id' => 2,
                'created_by' => 1,
                'name' => 'متوسط',
                'color' => null,
                'created_at' => '2025-06-09 23:45:59',
                'updated_at' => '2025-06-09 23:45:59'
            ],
            [
                'attribute_id' => 2,
                'created_by' => 1,
                'name' => '2XL',
                'color' => null,
                'created_at' => '2025-06-09 23:46:15',
                'updated_at' => '2025-06-09 23:46:15'
            ]
        ];

        foreach ($attributeValues as $valueData) {
            AttributeValue::create($valueData);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AttributeValue;
use App\Models\Attribute;

class AttributeValueSeeder extends Seeder
{
    public function run(): void
    {
        // ربط كل قيمة خاصية بخاصية موجودة
        $attributes = Attribute::all();
        foreach ($attributes as $attribute) {
            for ($i = 0; $i < 2; $i++) {
                AttributeValue::create([
                    'attribute_id' => $attribute->id,
                    'created_by' => 1,
                    'name' => 'قيمة ' . ($i + 1) . ' - ' . $attribute->name,
                    'color' => $i == 0 ? '#FF0000' : '#00FF00',
                ]);
            }
        }
    }
}

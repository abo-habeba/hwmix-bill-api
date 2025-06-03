<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductVariantAttribute;
use App\Models\ProductVariant;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Company;
use App\Models\User;

class ProductVariantAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        $user = User::first();
        $variants = ProductVariant::all();
        $attributes = Attribute::all();
        $attributeValues = AttributeValue::all();
        foreach ($variants as $variant) {
            foreach ($attributes as $attribute) {
                $value = $attributeValues->where('attribute_id', $attribute->id)->first();
                if ($value) {
                    ProductVariantAttribute::create([
                        'product_variant_id' => $variant->id,
                        'attribute_id' => $attribute->id,
                        'attribute_value_id' => $value->id,
                        'company_id' => $company?->id,
                        'created_by' => $user?->id,
                    ]);
                }
            }
        }
    }
}

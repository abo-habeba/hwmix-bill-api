<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // إنشاء 2 مستخدم فيك (واحد جملة وواحد قطاعي)
        User::factory()->create([
            'customer_type' => 'retail',
            'full_name' => 'عميل تجزئة',
            'nickname' => 'تجزئة',
            'username' => 'retail_user',
        ]); // عميل تجزئة

        User::factory()->create([
            'customer_type' => 'wholesale',
            'full_name' => 'عميل جملة',
            'nickname' => 'جملة',
            'username' => 'wholesale_user',
        ]); // عميل جملة
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // إنشاء 2 مستخدم فيك (واحد جملة وواحد قطاعي)
        User::factory()->create(['customer_type' => 'retail']); // عميل تجئة

        User::factory()->create(['customer_type' => 'wholesale']); // عميل جمبه
    }
}

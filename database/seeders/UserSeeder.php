<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'customer_type' => 'retail',
            'phone' => '01006444991',
            'email' => 'wael1.for@gmail.com',
            'full_name' => 'عميل تجزئة',
            'nickname' => 'تجزئة',
            'username' => 'retail_user',
            'password' => Hash::make('12345678'),
            'created_by' => 1,
            'company_id' => 1,
        ]);  // عميل تجزئة

        User::create([
            'customer_type' => 'wholesale',
            'phone' => '01006444992',
            'email' => 'wael2.for@gmail.com',
            'full_name' => 'عميل جملة',
            'nickname' => 'جملة',
            'username' => 'wholesale_user',
            'password' => Hash::make('12345678'),
            'created_by' => 1,
            'company_id' => 1,
        ]);  // عميل جملة

        User::create([
            'id' => 6,
            'phone' => '01006444993',
            'email' => 'wael.for@gmail.com',
            'username' => 'wael',
            'password' => Hash::make('12345678'),
            'nickname' => 'ابو ندي',
            'full_name' => 'وائل محمد احمد',
            'balance' => '0.00',
            'status' => '1',
            'customer_type' => 'wholesale',
            'created_by' => 1,
            'company_id' => 1,
        ]);
    }
}

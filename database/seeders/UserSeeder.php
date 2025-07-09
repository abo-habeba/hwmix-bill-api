<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'customer_type' => 'retail',
                'phone' => '01006444991',
                'email' => 'wael1.for@gmail.com',
                'full_name' => 'عميل تجزئة',
                'nickname' => 'تجزئة',
                'username' => 'retail_user',
                'password' => Hash::make('12345678'),
                'created_by' => 1,
                'company_id' => 1,
            ],
            [
                'customer_type' => 'wholesale',
                'phone' => '01006444992',
                'email' => 'wael2.for@gmail.com',
                'full_name' => 'عميل جملة',
                'nickname' => 'جملة',
                'username' => 'wholesale_user',
                'password' => Hash::make('12345678'),
                'created_by' => 1,
                'company_id' => 1,
            ],
        ];

        $companyIds = Company::pluck('id')->toArray();

        foreach ($users as $userData) {
            $user = User::create($userData);
            $pivotData = [];
            foreach ($companyIds as $companyId) {
                $pivotData[$companyId] = ['created_by' => $userData['created_by']];
            }
            $user->companies()->sync($pivotData);
            // إنشاء صناديق المستخدم الافتراضية لكل شركة
            $user->ensureCashBoxesForAllCompanies();
        }
    }
}

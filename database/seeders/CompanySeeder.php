<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::create([
            'name' => 'شركة نور الاسلام',
            'email' => 'info@alnoor.com',
            'phone' => '01006444992',
            'address' => '456 شارع نور الاسلام، القاهرة',
            'created_by' => 1,
        ]);
        Company::create([
            'name' => 'شركة هونكس',
            'email' => 'info@hwunex.com',
            'phone' => '01006444991',
            'address' => '123 شارع هونكس، القاهرة',
            'created_by' => 1,
        ]);
    }
}

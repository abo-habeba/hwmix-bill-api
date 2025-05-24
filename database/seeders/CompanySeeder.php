<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // إنشاء شركتين فيك
        Company::factory()->count(2)->create();
    }
}

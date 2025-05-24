<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvoiceType;

class InvoiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        InvoiceType::create([
            'name' => 'نقدي',
            'description' => 'فاتورة نقدية',
        ]);
        InvoiceType::create([
            'name' => 'تقسيط',
            'description' => 'فاتورة تقسيط',
        ]);
    }
}

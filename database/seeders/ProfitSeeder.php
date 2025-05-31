<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Profit;

class ProfitSeeder extends Seeder
{
    public function run(): void
    {
        Profit::create([
            'source_type' => 'sale_invoice',
            'source_id' => 1,
            'created_by' => 1,
            'customer_id' => 1,
            'company_id' => 1,
            'revenue_amount' => 1000,
            'cost_amount' => 700,
            'profit_amount' => 300,
            'note' => 'بيع منتج',
            'profit_date' => now()->toDateString(),
        ]);
        Profit::create([
            'source_type' => 'service_invoice',
            'source_id' => 2,
            'created_by' => 1,
            'customer_id' => 2,
            'company_id' => 1,
            'revenue_amount' => 500,
            'cost_amount' => 200,
            'profit_amount' => 300,
            'note' => 'خدمة استشارية',
            'profit_date' => now()->toDateString(),
        ]);
    }
}

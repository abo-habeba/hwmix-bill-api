<?php

namespace Database\Seeders;

use App\Models\Revenue;
use Illuminate\Database\Seeder;

class RevenueSeeder extends Seeder
{
    public function run(): void
    {
        Revenue::create([
            'source_type' => 'sale_invoice',
            'source_id' => 1,
            'user_id' => 1,
            'created_by' => 1,
            'wallet_id' => 1,
            'company_id' => 1,
            'amount' => 1000,
            'paid_amount' => 800,
            'remaining_amount' => 200,
            'payment_method' => 'cash',
            'note' => 'دفعة أولى',
            'revenue_date' => now()->toDateString(),
        ]);
        Revenue::create([
            'source_type' => 'service_invoice',
            'source_id' => 2,
            'user_id' => 2,
            'created_by' => 1,
            'wallet_id' => 1,
            'company_id' => 1,
            'amount' => 500,
            'paid_amount' => 500,
            'remaining_amount' => 0,
            'payment_method' => 'bank',
            'note' => 'خدمة استشارية',
            'revenue_date' => now()->toDateString(),
        ]);
    }
}

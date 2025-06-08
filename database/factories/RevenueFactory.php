<?php

namespace Database\Factories;

use App\Models\Revenue;
use Illuminate\Database\Eloquent\Factories\Factory;

class RevenueFactory extends Factory
{
    protected $model = Revenue::class;

    public function definition(): array
    {
        return [
            'source_type' => $this->faker->randomElement(['sale_invoice', 'service_invoice']),
            'source_id' => $this->faker->numberBetween(1, 10),
            'user_id' => 1,
            'created_by' => 1,
            'wallet_id' => 1,
            'company_id' => 1,
            'amount' => $this->faker->randomFloat(2, 100, 1000),
            'paid_amount' => $this->faker->randomFloat(2, 50, 1000),
            'remaining_amount' => 0,
            'payment_method' => $this->faker->randomElement(['cash', 'bank', 'vodafone_cash']),
            'note' => $this->faker->sentence,
            'revenue_date' => $this->faker->date(),
        ];
    }
}

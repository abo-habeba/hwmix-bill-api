<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['name' => 'نقدي', 'code' => 'cash', 'active' => true],
            ['name' => 'بطاقة ائتمان', 'code' => 'credit_card', 'active' => true],
            ['name' => 'تحويل بنكي', 'code' => 'bank_transfer', 'active' => true],
            ['name' => 'باي بال', 'code' => 'paypal', 'active' => true],
            ['name' => 'فودافون كاش', 'code' => 'vodafone_cash', 'active' => true],
            ['name' => 'اتصالات كاش', 'code' => 'etisalat_cash', 'active' => true],
            ['name' => 'اورنج كاش', 'code' => 'orange_cash', 'active' => true],
            ['name' => 'فوري', 'code' => 'fawry', 'active' => true],
        ];

        foreach ($methods as $method) {
            PaymentMethod::create($method);
        }
    }
}

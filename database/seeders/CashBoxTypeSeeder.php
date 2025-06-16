<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashBoxType;

class CashBoxTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'بطاقة ائتمان', 'description' => 'خزنة مخصصة لبطاقات الائتمان'],
            ['name' => 'تحويل بنكي', 'description' => 'خزنة مخصصة للتحويلات البنكية'],
            ['name' => 'باي بال', 'description' => 'خزنة مخصصة لباي بال'],
            ['name' => 'فودافون كاش', 'description' => 'خزنة مخصصة لفودافون كاش'],
            ['name' => 'اتصالات كاش', 'description' => 'خزنة مخصصة لاتصالات كاش'],
            ['name' => 'اورنج كاش', 'description' => 'خزنة مخصصة لاورنج كاش'],
            ['name' => 'فوري', 'description' => 'خزنة مخصصة لفوري'],
        ];

        foreach ($types as $type) {
            CashBoxType::create($type);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\CashBox;
use App\Models\CashBoxType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = Permission::pluck('name')->toArray();

        $existingCompany = Company::where('email', 'company@admin.com')->first();
        if (!$existingCompany) {
            $this->createSystemCompany();
        }

        $admin = User::where('email', 'admin@admin.com')->first();
        if (!$admin) {
            $this->createSystemOwner($permissions);
        } else {
            $admin->givePermissionTo($permissions);
        }
    }

    private function createSystemCompany()
    {
        Company::create([
            'name' => 'System Company',
            'description' => 'A description for the system company.',
            'field' => 'Technology',
            'owner_name' => 'System Owner',
            'address' => '123 System Street',
            'phone' => '010123456789',
            'email' => 'company@admin.com',
        ]);
    }

    private function createSystemOwner($permissions)
    {
        $user = User::create([
            'nickname' => 'System Owner',
            'email' => 'admin@admin.com',
            'full_name' => 'Admin',
            'username' => 'system_owner',
            'password' => bcrypt('12345678'),
            'phone' => '1234567890',
            'company_id' => 1,
        ]);
        $user->givePermissionTo($permissions);

        // مزامنة المستخدم مع جميع الشركات
        $companyIds = Company::pluck('id')->toArray();
        $pivotData = [];
        foreach ($companyIds as $companyId) {
            $pivotData[$companyId] = ['created_by' => $user->id];
        }
        $user->companies()->sync($pivotData);

        $cashBoxType = CashBoxType::create([
            'name' => 'نقدي',
            'description' => 'النوع الافتراضي للسيستم',
        ]);
        // إنشاء خزنة للمستخدم
        CashBox::create([
            'name' => 'نقدي',
            'balance' => 0,
            'cash_box_type_id' => $cashBoxType->id,
            'is_default' => true,
            'account_number' => $user->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'company_id' => $user->company_id,
        ]);
    }
}

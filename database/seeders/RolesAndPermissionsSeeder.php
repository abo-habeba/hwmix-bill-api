<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {

        Role::query()->delete();
        Permission::query()->delete();
        Artisan::call('permission:cache-reset');
        $permissions = [
            // Admins
            'super.admin', // صلاحيات السيستم كاملة
            'company.owner', // جميع صلاحيات الشركة التابع لها

            // Users
            'users', // صفحة المستخدمين
            'users.all', // جميع المستخدمين
            'users.all.own', // المستخدمين التابعين له
            'users.all.self', // عرض المستخدم الخاص به
            'users.show', // عرض تفاصيل أي مستخدم
            'users.show.own', // عرض تفاصيل المستخدمين التابعين له
            'users.show.self', // عرض تفاصيل حسابه الشخصي
            'users.create', // إنشاء مستخدم
            'users.update', // تعديل أي مستخدم
            'users.update.own', // تعديل المستخدمين التابعين له
            'users.update.self', // تعديل حسابه الشخصي
            'users.delete', // حذف أي مستخدم
            'users.delete.own', // حذف المستخدمين التابعين له
            'users.delete.self', // حذف حسابه الشخصي

            // Companys
            'companys', // صفحة الشركات
            'companys.all', // جميع الشركات
            'companys.all.own', // الشركات التابعين له
            'companys.all.self', // عرض الشركات الخاص به
            'companys.show', // عرض تفاصيل أي شركة
            'companys.show.own', // عرض تفاصيل الشركات التابعين له
            'companys.show.self', // عرض تفاصيل الشركة الخاصه به
            'companys.create', // إنشاء شركة
            'companys.update', // تعديل أي شركة
            'companys.update.own', // تعديل الشركات التابعين له
            'companys.update.self', // تعديل الشركه الخاصه به
            'companys.delete', // حذف أي شركة
            'companys.delete.own', // حذف الشركات التابعين له
            'companys.delete.self', // حذف الشركه الخاصه به

            // Roles
            'roles', // صفحة الأدوار
            'roles.all', // جميع الأدوار
            'roles.all.self', // عرض الأدوار الخاصة به
            'roles.all.own', // الأدوار التابعة له
            'roles.show', // عرض تفاصيل أي دور
            'roles.show.own', // تفاصيل الأدوار التابعة له
            'roles.show.self', // تفاصيل الأدوار الخاصه به
            'roles.create', // إنشاء دور
            'roles.update', // تعديل أي دور
            'roles.update.self', // تعديل الأدوار الخاصه به
            'roles.update.own', // تعديل الأدوار التابعة له
            'roles.delete', // حذف أي دور
            'roles.delete.self', // حذف الأدوار الخاصه به
            'roles.delete.own', // حذف الأدوار التابعة له

            // Logs
            'logs', // صفحة السجلات
            'logs.all', // عرض جميع السجلات
            'logs.all.own', // عرض السجلات التابعة له
            'logs.all.self', // عرض السجلات الخاصة به
            'logs.show', // عرض تفاصيل أي سجل
            'logs.show.own', // عرض تفاصيل السجلات التابعة له
            'logs.show.self', // عرض تفاصيل السجلات الخاصة به
            'logs.create', // إنشاء سجل
            'logs.update', // تعديل سجل
            'logs.update.own', // تعديل السجلات التابعة له
            'logs.update.self', // تعديل السجلات الخاصة به
            'logs.delete', // حذف السجلات
            'logs.delete.own', // حذف السجلات التابعة له
            'logs.delete.self', // حذف السجلات الخاصة به

            // Transactions
            'transaction', // صفحة المعاملات
            'transfer', // تحويل رصيد لأي مستخدم
            'deposit', // إيداع رصيد لأي مستخدم
            'withdraw', // سحب رصيد من أي مستخدم
            'transactions.all', // عرض جميع عمليات التحويل
            'transactions.all.own', // عرض عمليات التحويل التابعة له
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        $existingCompany = \App\Models\Company::where('email', 'company@admin.com')->first();
        if (!$existingCompany) {
            $this->createSystemCompany();
        }

        $admin = \App\Models\User::where('email', 'admin@admin.com')->first();
        if (!$admin) {
            $this->createSystemOwner($permissions);
        } else {
            $admin->givePermissionTo($permissions);
        }
    }
    private function createSystemCompany()
    {
        \App\Models\Company::create([
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
        $user = \App\Models\User::create([
            'nickname' => 'System Owner',
            'email' => 'admin@admin.com',
            'full_name' => 'Admin',
            'username' => 'system_owner',
            'password' => bcrypt('12345678'),
            'phone' => '1234567890',
            'company_id' => 1,
        ]);
        $user->givePermissionTo($permissions);

    }

}


<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\CashBox;
use App\Models\Company;
use App\Models\CashBoxType;
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

        $permissionsObject = [
            'adminPer' => [
                'super_admin', // صلاحيات السيستم كاملة
                'company_owner', // جميع صلاحيات الشركة التابع لها
            ],
            'cashbox' => [
                // CashBox
                'cashbox', // صفحة الصناديق
                'cashbox_all', // جميع الصناديق
                'cashbox_all_own', // الصناديق التابعة له
                'cashbox_all_self', // عرض الصندوق الخاص به
                'cashbox_show', // عرض تفاصيل أي صندوق
                'cashbox_show_own', // عرض تفاصيل الصناديق التابعة له
                'cashbox_show_self', // عرض تفاصيل الصندوق الخاص به
                'cashbox_create', // إنشاء صندوق
                'cashbox_update', // تعديل أي صندوق
                'cashbox_update_own', // تعديل الصناديق التابعة له
                'cashbox_update_self', // تعديل الصندوق الخاص به
                'cashbox_delete', // حذف أي صندوق
                'cashbox_delete_own', // حذف الصناديق التابعة له
                'cashbox_delete_self', // حذف الصندوق الخاص به
            ],
            'CashBoxType' => [
                // CashBoxType
                'CashBoxType', // صفحة أنواع الصناديق
                'CashBoxType_all', // جميع أنواع الصناديق
                'CashBoxType_all_own', // أنواع الصناديق التابعة له
                'CashBoxType_all_self', // عرض نوع الصندوق الخاص به
                'CashBoxType_show', // عرض تفاصيل أي نوع صندوق
                'CashBoxType_show_own', // عرض تفاصيل أنواع الصناديق التابعة له
                'CashBoxType_show_self', // عرض تفاصيل نوع الصندوق الخاص به
                'CashBoxType_create', // إنشاء نوع صندوق
                'CashBoxType_update', // تعديل أي نوع صندوق
                'CashBoxType_update_own', // تعديل أنواع الصناديق التابعة له
                'CashBoxType_update_self', // تعديل نوع الصندوق الخاص به
                'CashBoxType_delete', // حذف أي نوع صندوق
                'CashBoxType_delete_own', // حذف أنواع الصناديق التابعة له
                'CashBoxType_delete_self', // حذف نوع الصندوق الخاص به
            ],

            'users' => [
                'users', // صفحة المستخدمين
                'users_all', // جميع المستخدمين
                'users_all_own', // المستخدمين التابعين له
                'users_all_self', // عرض المستخدم الخاص به
                'users_show', // عرض تفاصيل أي مستخدم
                'users_show_own', // عرض تفاصيل المستخدمين التابعين له
                'users_show_self', // عرض تفاصيل حسابه الشخصي
                'users_create', // إنشاء مستخدم
                'users_update', // تعديل أي مستخدم
                'users_update_own', // تعديل المستخدمين التابعين له
                'users_update_self', // تعديل حسابه الشخصي
                'users_delete', // حذف أي مستخدم
                'users_delete_own', // حذف المستخدمين التابعين له
                'users_delete_self', // حذف حسابه الشخصي
            ],
            'companys' => [
                'companys', // صفحة الشركات
                'companys_all', // جميع الشركات
                'companys_all_own', // الشركات التابعين له
                'companys_all_self', // عرض الشركات الخاص به
                'companys_show', // عرض تفاصيل أي شركة
                'companys_show_own', // عرض تفاصيل الشركات التابعين له
                'companys_show_self', // عرض تفاصيل الشركة الخاصه به
                'companys_create', // إنشاء شركة
                'companys_update', // تعديل أي شركة
                'companys_update_own', // تعديل الشركات التابعين له
                'companys_update_self', // تعديل الشركه الخاصه به
                'companys_delete', // حذف أي شركة
                'companys_delete_own', // حذف الشركات التابعين له
                'companys_delete_self', // حذف الشركه الخاصه به
            ],
            'roles' => [
                // Roles
                'roles', // صفحة الأدوار
                'roles_all', // جميع الأدوار
                'roles_all_self', // عرض الأدوار الخاصة به
                'roles_all_own', // الأدوار التابعة له
                'roles_show', // عرض تفاصيل أي دور
                'roles_show_own', // تفاصيل الأدوار التابعة له
                'roles_show_self', // تفاصيل الأدوار الخاصه به
                'roles_create', // إنشاء دور
                'roles_update', // تعديل أي دور
                'roles_update_self', // تعديل الأدوار الخاصه به
                'roles_update_own', // تعديل الأدوار التابعة له
                'roles_delete', // حذف أي دور
                'roles_delete_self', // حذف الأدوار الخاصه به
                'roles_delete_own', // حذف الأدوار التابعة له
            ],
            'logs' => [
                'logs', // صفحة السجلات
                'logs_all', // عرض جميع السجلات
                'logs_all_own', // عرض السجلات التابعة له
                'logs_all_self', // عرض السجلات الخاصة به
                'logs_show', // عرض تفاصيل أي سجل
                'logs_show_own', // عرض تفاصيل السجلات التابعة له
                'logs_show_self', // عرض تفاصيل السجلات الخاصة به
                'logs_create', // إنشاء سجل
                'logs_update', // تعديل سجل
                'logs_update_own', // تعديل السجلات التابعة له
                'logs_update_self', // تعديل السجلات الخاصة به
                'logs_delete', // حذف السجلات
                'logs_delete_own', // حذف السجلات التابعة له
                'logs_delete_self', // حذف السجلات الخاصة به
            ],
            'transaction' => [
                'transaction', // صفحة المعاملات
                'transfer', // تحويل رصيد لأي مستخدم
                'deposit', // إيداع رصيد لأي مستخدم
                'withdraw', // سحب رصيد من أي مستخدم
                'transactions_all', // عرض جميع عمليات التحويل
                'transactions_all_own', // عرض عمليات التحويل التابعة له
            ],
            'cash_box_types' => [
                'cash_box_types', // صفحة أنواع الخزنة
                'cash_box_types_all', // جميع أنواع الخزنة
                'cash_box_types_all_own', // أنواع الخزنة التابعة له
                'cash_box_types_all_self', // عرض نوع الخزنة الخاص به
                'cash_box_types_show', // عرض تفاصيل أي نوع خزنة
                'cash_box_types_show_own', // عرض تفاصيل أنواع الخزنة التابعة له
                'cash_box_types_show_self', // عرض تفاصيل نوع الخزنة الخاص به
                'cash_box_types_create', // إنشاء نوع خزنة
                'cash_box_types_update', // تعديل أي نوع خزنة
                'cash_box_types_update_own', // تعديل أنواع الخزنة التابعة له
                'cash_box_types_update_self', // تعديل نوع الخزنة الخاص به
                'cash_box_types_delete', // حذف أي نوع خزنة
                'cash_box_types_delete_own', // حذف أنواع الخزنة التابعة له
                'cash_box_types_delete_self', // حذف نوع الخزنة الخاص به
            ],
        ];

        $permissions = [];

        foreach ($permissionsObject as $group) {
            $permissions = array_merge($permissions, $group);
        }

        Permission::insert(array_map(fn($permission) => ['name' => $permission, 'guard_name' => 'web'], $permissions));

        // foreach ($permissions as $permission) {
        //     Permission::create(['name' => $permission]);
        // }
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


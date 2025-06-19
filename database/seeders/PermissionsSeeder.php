<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    public function run()
    {
        Role::query()->delete();
        Permission::query()->delete();
        Artisan::call('permission:cache-reset');

        $permissionsObject = [
            'adminPer' => [
                'super_admin',  // صلاحيات السيستم كاملة
                'company_owner',  // جميع صلاحيات الشركة التابع لها
            ],
            'cashbox' => [
                // CashBox
                'cashbox',  // صفحة الصناديق
                'cashbox_all',  // جميع الصناديق
                'cashbox_all_own',  // الصناديق التابعة له
                'cashbox_all_self',  // عرض الصندوق الخاص به
                'cashbox_show',  // عرض تفاصيل أي صندوق
                'cashbox_show_own',  // عرض تفاصيل الصناديق التابعة له
                'cashbox_show_self',  // عرض تفاصيل الصندوق الخاص به
                'cashbox_create',  // إنشاء صندوق
                'cashbox_update',  // تعديل أي صندوق
                'cashbox_update_own',  // تعديل الصناديق التابعة له
                'cashbox_update_self',  // تعديل الصندوق الخاص به
                'cashbox_delete',  // حذف أي صندوق
                'cashbox_delete_own',  // حذف الصناديق التابعة له
                'cashbox_delete_self',  // حذف الصندوق الخاص به
            ],
            'CashBoxType' => [
                // CashBoxType
                'CashBoxType',  // صفحة أنواع الصناديق
                'CashBoxType_all',  // جميع أنواع الصناديق
                'CashBoxType_all_own',  // أنواع الصناديق التابعة له
                'CashBoxType_all_self',  // عرض نوع الصندوق الخاص به
                'CashBoxType_show',  // عرض تفاصيل أي نوع صندوق
                'CashBoxType_show_own',  // عرض تفاصيل أنواع الصناديق التابعة له
                'CashBoxType_show_self',  // عرض تفاصيل نوع الصندوق الخاص به
                'CashBoxType_create',  // إنشاء نوع صندوق
                'CashBoxType_update',  // تعديل أي نوع صندوق
                'CashBoxType_update_own',  // تعديل أنواع الصناديق التابعة له
                'CashBoxType_update_self',  // تعديل نوع الصندوق الخاص به
                'CashBoxType_delete',  // حذف أي نوع صندوق
                'CashBoxType_delete_own',  // حذف أنواع الصناديق التابعة له
                'CashBoxType_delete_self',  // حذف نوع الصندوق الخاص به
            ],
            'users' => [
                'users',  // صفحة المستخدمين
                'users_all',  // جميع المستخدمين
                'users_all_own',  // المستخدمين التابعين له
                'users_all_self',  // عرض المستخدم الخاص به
                'users_show',  // عرض تفاصيل أي مستخدم
                'users_show_own',  // عرض تفاصيل المستخدمين التابعين له
                'users_show_self',  // عرض تفاصيل حسابه الشخصي
                'users_create',  // إنشاء مستخدم
                'users_update',  // تعديل أي مستخدم
                'users_update_own',  // تعديل المستخدمين التابعين له
                'users_update_self',  // تعديل حسابه الشخصي
                'users_delete',  // حذف أي مستخدم
                'users_delete_own',  // حذف المستخدمين التابعين له
                'users_delete_self',  // حذف حسابه الشخصي
            ],
            'companys' => [
                'companys',  // صفحة الشركات
                'companys_all',  // جميع الشركات
                'companys_all_own',  // الشركات التابعين له
                'companys_all_self',  // عرض الشركات الخاص به
                'companys_show',  // عرض تفاصيل أي شركة
                'companys_show_own',  // عرض تفاصيل الشركات التابعين له
                'companys_show_self',  // عرض تفاصيل الشركة الخاصه به
                'companys_create',  // إنشاء شركة
                'companys_update',  // تعديل أي شركة
                'companys_update_own',  // تعديل الشركات التابعين له
                'companys_update_self',  // تعديل الشركه الخاصه به
                'companys_delete',  // حذف أي شركة
                'companys_delete_own',  // حذف الشركات التابعين له
                'companys_delete_self',  // حذف الشركه الخاصه به
            ],
            'roles' => [
                // Roles
                'roles',  // صفحة الأدوار
                'roles_all',  // جميع الأدوار
                'roles_all_self',  // عرض الأدوار الخاصة به
                'roles_all_own',  // الأدوار التابعة له
                'roles_show',  // عرض تفاصيل أي دور
                'roles_show_own',  // تفاصيل الأدوار التابعة له
                'roles_show_self',  // تفاصيل الأدوار الخاصه به
                'roles_create',  // إنشاء دور
                'roles_update',  // تعديل أي دور
                'roles_update_self',  // تعديل الأدوار الخاصه به
                'roles_update_own',  // تعديل الأدوار التابعة له
                'roles_delete',  // حذف أي دور
                'roles_delete_self',  // حذف الأدوار الخاصه به
                'roles_delete_own',  // حذف الأدوار التابعة له
            ],
            'logs' => [
                'logs',  // صفحة السجلات
                'logs_all',  // عرض جميع السجلات
                'logs_all_own',  // عرض السجلات التابعة له
                'logs_all_self',  // عرض السجلات الخاصة به
                'logs_show',  // عرض تفاصيل أي سجل
                'logs_show_own',  // عرض تفاصيل السجلات التابعة له
                'logs_show_self',  // عرض تفاصيل السجلات الخاصة به
                'logs_create',  // إنشاء سجل
                'logs_update',  // تعديل سجل
                'logs_update_own',  // تعديل السجلات التابعة له
                'logs_update_self',  // تعديل السجلات الخاصة به
                'logs_delete',  // حذف السجلات
                'logs_delete_own',  // حذف السجلات التابعة له
                'logs_delete_self',  // حذف السجلات الخاصة به
            ],
            'transaction' => [
                'transaction',  // صفحة المعاملات
                'transfer',  // تحويل رصيد لأي مستخدم
                'deposit',  // إيداع رصيد لأي مستخدم
                'withdraw',  // سحب رصيد من أي مستخدم
            ],
            'invoices' => [
                'invoices',  // صفحة الفواتير
                'invoices_all',  // عرض جميع الفواتير
                'invoices_create',  // إنشاء فاتورة
                'invoices_update',  // تعديل فاتورة
                'invoices_delete',  // حذف فاتورة
            ],
            'products' => [
                'products',  // صفحة المنتجات
                'products_all',  // عرض جميع المنتجات
                'products_create',  // إنشاء منتج
                'products_update',  // تعديل منتج
                'products_delete',  // حذف منتج
            ],
            'installments' => [
                'installments',  // صفحة الأقساط
                'installments_all',  // عرض جميع الأقساط
                'installments_create',  // إنشاء قسط
                'installments_update',  // تعديل قسط
                'installments_delete',  // حذف قسط
            ],
            'warehouses' => [
                'warehouses',  // صفحة المخازن
                'warehouses_all',  // عرض جميع المخازن
                'warehouses_create',  // إنشاء مخزن
                'warehouses_update',  // تعديل مخزن
                'warehouses_delete',  // حذف مخزن
            ],
            'categories' => [
                'categories',  // صفحة الفئات
                'categories_all',  // عرض جميع الفئات
                'categories_create',  // إنشاء فئة
                'categories_update',  // تعديل فئة
                'categories_delete',  // حذف فئة
            ],
            'brands' => [
                'brands',  // صفحة العلامات التجارية
                'brands_all',  // عرض جميع العلامات التجارية
                'brands_create',  // إنشاء علامة تجارية
                'brands_update',  // تعديل علامة تجارية
                'brands_delete',  // حذف علامة تجارية
            ],
            'attributes' => [
                'attributes',  // صفحة السمات
                'attributes_all',  // عرض جميع السمات
                'attributes_create',  // إنشاء سمة
                'attributes_update',  // تعديل سمة
                'attributes_delete',  // حذف سمة
            ],
            'attribute_values' => [
                'attribute_values',  // صفحة قيم السمات
                'attribute_values_all',  // عرض جميع قيم السمات
                'attribute_values_create',  // إنشاء قيمة سمة
                'attribute_values_update',  // تعديل قيمة سمة
                'attribute_values_delete',  // حذف قيمة سمة
            ],
            'subscriptions' => [
                'subscriptions',  // صفحة الاشتراكات
                'subscriptions_all',  // عرض جميع الاشتراكات
                'subscriptions_create',  // إنشاء اشتراك
                'subscriptions_update',  // تعديل اشتراك
                'subscriptions_delete',  // حذف اشتراك
            ],
        ];

        foreach ($permissionsObject as $group => $permissions) {
            Permission::insert(array_map(fn($permission) => ['name' => $permission, 'guard_name' => 'web'], $permissions));
        }
    }
}

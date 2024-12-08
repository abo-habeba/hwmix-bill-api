<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // حذف الأدوار والصلاحيات القديمة
        // Permission::truncate();
        // Role::truncate();
        Role::query()->delete();
        Permission::query()->delete();


        // تعريف الصلاحيات بعد التعديلات
        $permissions = [

            // User permissions
            'users.all',              // عرض جميع المستخدمين
            'users.show',             // عرض تفاصيل أي مستخدم
            'users.create',           // إنشاء مستخدم
            'users.update',           // تعديل أي مستخدم
            'users.delete',           // حذف أي مستخدم
            'users.all.own',          // عرض المستخدمين التابعين له فقط
            'users.show.own',         // عرض تفاصيل المستخدمين التابعين له فقط
            'users.update.own',       // تعديل المستخدمين التابعين له فقط
            'users.delete.own',       // حذف المستخدمين التابعين له فقط

            // roles & Permission assignment
            'permissions.all',        // عرض جميع الصلاحيات
            'permissions.assign',     // إسناد الصلاحيات لأي مستخدم في النظام
            'permissions.revoke',     // إلغاء الصلاحيات من أي مستخدم في النظام
            'permissions.assign.own', // إسناد الصلاحيات للمستخدمين التابعين فقط
            'permissions.revoke.own', // إلغاء الصلاحيات من المستخدمين التابعين فقط
            'roles.all',              // عرض جميع الأدوار
            'roles.create',           // إنشاء دور جديد
            'roles.update',           // تعديل أي دور
            'roles.delete',           // حذف أي دور
            'roles.show.own',         // عرض جميع الأدوار
            'roles.update.own',       // تعديل دور التابعين له
            'roles.delete.own',       // حذف دور التابعين له

            // Product permissions
            'products.all',           // عرض جميع المنتجات
            'products.show',          // عرض تفاصيل أي منتج
            'products.create',        // إضافة منتج جديد
            'products.update',        // تعديل منتج
            'products.delete',        // حذف منتج
            'products.all.own',       // عرض المنتجات التابعة له فقط
            'products.show.own',      // عرض تفاصيل المنتجات التابعة له فقط
            'products.update.own',    // تعديل المنتجات التابعة له فقط
            'products.delete.own',    // حذف المنتجات التابعة له فقط

            // Order permissions
            'orders.all',             // عرض جميع الطلبات
            'orders.show',            // عرض تفاصيل أي طلب
            'orders.create',          // إنشاء طلب جديد
            'orders.update',          // تعديل طلب
            'orders.delete',          // حذف طلب
            'orders.all.own',         // عرض الطلبات التابعة له فقط
            'orders.show.own',        // عرض تفاصيل الطلبات التابعة له فقط
            'orders.update.own',      // تعديل الطلبات التابعة له فقط
            'orders.delete.own',      // حذف الطلبات التابعة له فقط

            // Inventory permissions
            'inventory.all',          // عرض جميع المخزونات
            'inventory.show',         // عرض تفاصيل أي مخزون
            'inventory.add',          // إضافة مخزون جديد
            'inventory.update',       // تعديل مخزون
            'inventory.delete',       // حذف مخزون
            'inventory.all.own',      // عرض المخزونات التابعة له فقط
            'inventory.show.own',     // عرض تفاصيل المخزونات التابعة له فقط
            'inventory.update.own',   // تعديل المخزونات التابعة له فقط
            'inventory.delete.own',   // حذف المخزونات التابعة له فقط

            // Supplier permissions
            'suppliers.all',          // عرض جميع الموردين
            'suppliers.show',         // عرض تفاصيل أي مورد
            'suppliers.create',       // إضافة مورد جديد
            'suppliers.update',       // تعديل مورد
            'suppliers.delete',       // حذف مورد
            'suppliers.all.own',      // عرض الموردين التابعين له فقط
            'suppliers.show.own',     // عرض تفاصيل الموردين التابعين له فقط
            'suppliers.update.own',   // تعديل الموردين التابعين له فقط
            'suppliers.delete.own',   // حذف الموردين التابعين له فقط

            // Category permissions
            'categories.all',         // عرض جميع الفئات
            'categories.show',        // عرض تفاصيل أي فئة
            'categories.create',      // إضافة فئة جديدة
            'categories.update',      // تعديل فئة
            'categories.delete',      // حذف فئة
            'categories.all.own',     // عرض الفئات التابعة له فقط
            'categories.show.own',    // عرض تفاصيل الفئات التابعة له فقط
            'categories.update.own',  // تعديل الفئات التابعة له فقط
            'categories.delete.own',  // حذف الفئات التابعة له فقط

            // Payment permissions
            'payments.all',           // عرض جميع المدفوعات
            'payments.show',          // عرض تفاصيل أي مدفوعات
            'payments.create',        // إنشاء مدفوعات جديدة
            'payments.update',        // تعديل مدفوعات
            'payments.delete',        // حذف مدفوعات
            'payments.all.own',       // عرض المدفوعات التابعة له فقط
            'payments.show.own',      // عرض تفاصيل المدفوعات التابعة له فقط
            'payments.update.own',    // تعديل المدفوعات التابعة له فقط
            'payments.delete.own',    // حذف المدفوعات التابعة له فقط

            // Report permissions
            'reports.all',            // عرض جميع التقارير
            'reports.show',           // عرض تفاصيل أي تقرير
            'reports.create',         // إنشاء تقرير جديد
            'reports.update',         // تعديل تقرير
            'reports.delete',         // حذف تقرير
            'reports.all.own',        // عرض التقارير التابعة له فقط
            'reports.show.own',       // عرض تفاصيل التقارير التابعة له فقط
            'reports.update.own',     // تعديل التقارير التابعة له فقط
            'reports.delete.own',     // حذف التقارير التابعة له فقط

            // Customer permissions
            'customers.all',          // عرض جميع العملاء
            'customers.show',         // عرض تفاصيل أي عميل
            'customers.create',       // إضافة عميل جديد
            'customers.update',       // تعديل عميل
            'customers.delete',       // حذف عميل
            'customers.all.own',      // عرض العملاء التابعة له فقط
            'customers.show.own',     // عرض تفاصيل العملاء التابعة له فقط
            'customers.update.own',    // تعديل العملاء التابعة له فقط
            'customers.delete.own',    // حذف العملاء التابعة له فقط

            // Discount permissions
            'discounts.all',          // عرض جميع الخصومات
            'discounts.show',         // عرض تفاصيل أي خصم
            'discounts.create',       // إضافة خصم جديد
            'discounts.update',       // تعديل خصم
            'discounts.delete',       // حذف خصم
            'discounts.all.own',      // عرض الخصومات التابعة له فقط
            'discounts.show.own',     // عرض تفاصيل الخصومات التابعة له فقط
            'discounts.update.own',   // تعديل الخصومات التابعة له فقط
            'discounts.delete.own',   // حذف الخصومات التابعة له فقط

            // Shipment permissions
            'shipments.all',          // عرض جميع الشحنات
            'shipments.show',         // عرض تفاصيل أي شحنة
            'shipments.create',       // إنشاء شحنة جديدة
            'shipments.update',       // تعديل شحنة
            'shipments.delete',       // حذف شحنة
            'shipments.all.own',      // عرض الشحنات التابعة له فقط
            'shipments.show.own',     // عرض تفاصيل الشحنات التابعة له فقط
            'shipments.update.own',   // تعديل الشحنات التابعة له فقط
            'shipments.delete.own',   // حذف الشحنات التابعة له فقط
            'employee',
        ];

        // إضافة الصلاحيات
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }



        // الآن، يمكننا إنشاء المستخدمين مع الأدوار المناسبة
        $this->createSystemOwner($permissions);
        $this->createCompanyOwner();
        $this->createEmployee();
    }

    // إنشاء صاحب السيستم
    private function createSystemOwner($permissions)
    {
        $user = \App\Models\User::factory()->create([
            'name' => 'System Owner',
            'email' => 'admin@admin.com',
            'first_name' => 'Admin',
            'last_name' => 'System',
            'username' => 'system_owner',
            'password' => bcrypt('12345678'),
            'phone' => '1234567890',
        ]);
        $user->givePermissionTo($permissions);
    }

    // إنشاء صاحب الشركة
    private function createCompanyOwner()
    {
        $permissions = [

            // User permissions
            'users.create',           // إنشاء مستخدم
            'users.delete',           // حذف أي مستخدم
            'users.all.own',          // عرض المستخدمين التابعين له فقط
            'users.show.own',         // عرض تفاصيل المستخدمين التابعين له فقط
            'users.update.own',       // تعديل المستخدمين التابعين له فقط
            'users.delete.own',       // حذف المستخدمين التابعين له فقط

            // roles & Permission assignment
            'permissions.all',        // عرض جميع الصلاحيات
            'permissions.assign.own', // إسناد الصلاحيات للمستخدمين التابعين فقط
            'permissions.revoke.own', // إلغاء الصلاحيات من المستخدمين التابعين فقط
            'roles.all',              // عرض جميع الأدوار
            'roles.create',           // إنشاء دور جديد
            'roles.show.own',         // عرض جميع الأدوار التابعه له
            'roles.update.own',       // تعديل دور التابعين له
            'roles.delete.own',       // حذف دور التابعين له

            // Product permissions
            'products.create',        // إضافة منتج جديد
            'products.all.own',       // عرض المنتجات التابعة له فقط
            'products.show.own',      // عرض تفاصيل المنتجات التابعة له فقط
            'products.update.own',    // تعديل المنتجات التابعة له فقط
            'products.delete.own',    // حذف المنتجات التابعة له فقط

            // Order permissions
            'orders.create',          // إنشاء طلب جديد
            'orders.all.own',         // عرض الطلبات التابعة له فقط
            'orders.show.own',        // عرض تفاصيل الطلبات التابعة له فقط
            'orders.update.own',      // تعديل الطلبات التابعة له فقط
            'orders.delete.own',      // حذف الطلبات التابعة له فقط

            // Inventory permissions
            'inventory.add',          // إضافة مخزون جديد
            'inventory.all.own',      // عرض المخزونات التابعة له فقط
            'inventory.show.own',     // عرض تفاصيل المخزونات التابعة له فقط
            'inventory.update.own',   // تعديل المخزونات التابعة له فقط
            'inventory.delete.own',   // حذف المخزونات التابعة له فقط

            // Supplier permissions
            'suppliers.create',       // إضافة مورد جديد
            'suppliers.all.own',      // عرض الموردين التابعين له فقط
            'suppliers.show.own',     // عرض تفاصيل الموردين التابعين له فقط
            'suppliers.update.own',   // تعديل الموردين التابعين له فقط
            'suppliers.delete.own',   // حذف الموردين التابعين له فقط

            // Category permissions
            'categories.create',      // إضافة فئة جديدة
            'categories.all.own',     // عرض الفئات التابعة له فقط
            'categories.show.own',    // عرض تفاصيل الفئات التابعة له فقط
            'categories.update.own',  // تعديل الفئات التابعة له فقط
            'categories.delete.own',  // حذف الفئات التابعة له فقط

            // Payment permissions
            'payments.create',        // إنشاء مدفوعات جديدة
            'payments.all.own',       // عرض المدفوعات التابعة له فقط
            'payments.show.own',      // عرض تفاصيل المدفوعات التابعة له فقط
            'payments.update.own',    // تعديل المدفوعات التابعة له فقط
            'payments.delete.own',    // حذف المدفوعات التابعة له فقط

            // Report permissions
            'reports.create',         // إنشاء تقرير جديد
            'reports.all.own',        // عرض التقارير التابعة له فقط
            'reports.show.own',       // عرض تفاصيل التقارير التابعة له فقط
            'reports.update.own',     // تعديل التقارير التابعة له فقط
            'reports.delete.own',     // حذف التقارير التابعة له فقط

            // Customer permissions
            'customers.create',       // إضافة عميل جديد
            'customers.all.own',      // عرض العملاء التابعة له فقط
            'customers.show.own',     // عرض تفاصيل العملاء التابعة له فقط
            'customers.update.own',    // تعديل العملاء التابعة له فقط
            'customers.delete.own',    // حذف العملاء التابعة له فقط

            // Discount permissions
            'discounts.create',       // إضافة خصم جديد
            'discounts.all.own',      // عرض الخصومات التابعة له فقط
            'discounts.show.own',     // عرض تفاصيل الخصومات التابعة له فقط
            'discounts.update.own',   // تعديل الخصومات التابعة له فقط
            'discounts.delete.own',   // حذف الخصومات التابعة له فقط

            // Shipment permissions
            'shipments.create',       // إنشاء شحنة جديدة
            'shipments.all.own',      // عرض الشحنات التابعة له فقط
            'shipments.show.own',     // عرض تفاصيل الشحنات التابعة له فقط
            'shipments.update.own',   // تعديل الشحنات التابعة له فقط
            'shipments.delete.own',   // حذف الشحنات التابعة له فقط
        ];
        $user = \App\Models\User::factory()->create([
            'name' => 'Company Owner',
            'email' => 'companyowner@company.com',
            'first_name' => 'Company',
            'last_name' => 'Owner',
            'username' => 'Company_owner',
            'password' => bcrypt('12345678'),  // تغيير كلمة المرور هنا
            'phone' => '1234567890',  // إضافة رقم الهاتف
        ]);
        $user->givePermissionTo($permissions);
    }
    //انشاء موظف في الشركه
    private function createEmployee()
    {
        $permissions = [

            // User permissions
            'users.create',           // إنشاء مستخدم
            'users.delete',           // حذف أي مستخدم
            'users.all.own',          // عرض المستخدمين التابعين له فقط
            'users.show.own',         // عرض تفاصيل المستخدمين التابعين له فقط
            'users.update.own',       // تعديل المستخدمين التابعين له فقط
            'users.delete.own',       // حذف المستخدمين التابعين له فقط

            // Product permissions
            'products.create',        // إضافة منتج جديد
            'products.all.own',       // عرض المنتجات التابعة له فقط
            'products.show.own',      // عرض تفاصيل المنتجات التابعة له فقط
            'products.update.own',    // تعديل المنتجات التابعة له فقط
            'products.delete.own',    // حذف المنتجات التابعة له فقط

            // Order permissions
            'orders.create',          // إنشاء طلب جديد
            'orders.all.own',         // عرض الطلبات التابعة له فقط
            'orders.show.own',        // عرض تفاصيل الطلبات التابعة له فقط
            'orders.update.own',      // تعديل الطلبات التابعة له فقط
            'orders.delete.own',      // حذف الطلبات التابعة له فقط

            // Inventory permissions
            'inventory.add',          // إضافة مخزون جديد
            'inventory.all.own',      // عرض المخزونات التابعة له فقط
            'inventory.show.own',     // عرض تفاصيل المخزونات التابعة له فقط
            'inventory.update.own',   // تعديل المخزونات التابعة له فقط
            'inventory.delete.own',   // حذف المخزونات التابعة له فقط

            // Supplier permissions
            'suppliers.create',       // إضافة مورد جديد
            'suppliers.all.own',      // عرض الموردين التابعين له فقط
            'suppliers.show.own',     // عرض تفاصيل الموردين التابعين له فقط
            'suppliers.update.own',   // تعديل الموردين التابعين له فقط
            'suppliers.delete.own',   // حذف الموردين التابعين له فقط

            // Category permissions
            'categories.create',      // إضافة فئة جديدة
            'categories.all.own',     // عرض الفئات التابعة له فقط
            'categories.show.own',    // عرض تفاصيل الفئات التابعة له فقط
            'categories.update.own',  // تعديل الفئات التابعة له فقط
            'categories.delete.own',  // حذف الفئات التابعة له فقط

            // Payment permissions
            'payments.create',        // إنشاء مدفوعات جديدة
            'payments.all.own',       // عرض المدفوعات التابعة له فقط
            'payments.show.own',      // عرض تفاصيل المدفوعات التابعة له فقط
            'payments.update.own',    // تعديل المدفوعات التابعة له فقط
            'payments.delete.own',    // حذف المدفوعات التابعة له فقط

            // Report permissions
            'reports.create',         // إنشاء تقرير جديد
            'reports.all.own',        // عرض التقارير التابعة له فقط
            'reports.show.own',       // عرض تفاصيل التقارير التابعة له فقط
            'reports.update.own',     // تعديل التقارير التابعة له فقط
            'reports.delete.own',     // حذف التقارير التابعة له فقط

            // Customer permissions
            'customers.create',       // إضافة عميل جديد
            'customers.all.own',      // عرض العملاء التابعة له فقط
            'customers.show.own',     // عرض تفاصيل العملاء التابعة له فقط
            'customers.update.own',    // تعديل العملاء التابعة له فقط
            'customers.delete.own',    // حذف العملاء التابعة له فقط

            // Discount permissions
            'discounts.create',       // إضافة خصم جديد
            'discounts.all.own',      // عرض الخصومات التابعة له فقط
            'discounts.show.own',     // عرض تفاصيل الخصومات التابعة له فقط
            'discounts.update.own',   // تعديل الخصومات التابعة له فقط
            'discounts.delete.own',   // حذف الخصومات التابعة له فقط

            // Shipment permissions
            'shipments.create',       // إنشاء شحنة جديدة
            'shipments.all.own',      // عرض الشحنات التابعة له فقط
            'shipments.show.own',     // عرض تفاصيل الشحنات التابعة له فقط
            'shipments.update.own',   // تعديل الشحنات التابعة له فقط
            'shipments.delete.own',   // حذف الشحنات التابعة له فقط
        ];
        $user = \App\Models\User::factory()->create([
            'name' => 'Employee Company',
            'email' => 'Employee@Employee.com',
            'first_name' => 'Employee',
            'last_name' => 'Employee Company',
            'username' => 'Employee',
            'password' => bcrypt('12345678'),  // تغيير كلمة المرور هنا
            'phone' => '1234567890',  // إضافة رقم الهاتف
        ]);
        $user->givePermissionTo($permissions);
    }

}


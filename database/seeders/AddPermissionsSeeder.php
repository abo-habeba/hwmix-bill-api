<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;  // تأكد من استخدام موديل الصلاحيات الصحيح

class AddPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // جلب جميع تعريفات الصلاحيات من ملف config/permissions_keys.php
        $permissionsConfig = config('permissions_keys');

        // جلب أسماء الصلاحيات الموجودة بالفعل
        $existingPermissions = Permission::pluck('name')->toArray();

        // مصفوفة لتخزين جميع مفاتيح الصلاحيات التي سيتم إضافتها
        $permissionsToSeed = [];

        // المرور على كل كيان (entity) وكل فعل (action) داخل ملف الصلاحيات
        foreach ($permissionsConfig as $entity => $actions) {
            foreach ($actions as $actionData) {
                // التأكد من أن المفتاح 'key' موجود لضمان عدم وجود أخطاء
                if (isset($actionData['key'])) {
                    // إضافة فقط إذا لم تكن موجودة مسبقًا
                    if (!in_array($actionData['key'], $existingPermissions)) {
                        $permissionsToSeed[] = [
                            'name' => $actionData['key'],
                            'guard_name' => 'web',
                            'created_at' => now(),  // إضافة timestamp
                            'updated_at' => now(),  // إضافة timestamp
                        ];
                    }
                }
            }
        }

        // إدراج جميع الصلاحيات الجديدة فقط في جدول الصلاحيات
        if (!empty($permissionsToSeed)) {
            Permission::insert($permissionsToSeed);
        }

        // تشغيل Seeder الخاص بالأدوار والصلاحيات بعد الصلاحيات
        $this->call(RolesAndPermissionsSeeder::class);

        $this->command->info('Permissions seeded successfully!');
    }
}

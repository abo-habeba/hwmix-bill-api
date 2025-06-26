<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;  // تأكد من استخدام موديل الصلاحيات الصحيح
use Spatie\Permission\PermissionRegistrar;

class AddPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // جلب جميع تعريفات الصلاحيات من ملف config/permissions_keys.php
        $permissionsConfig = config('permissions_keys');

        // جلب أسماء الصلاحيات الموجودة بالفعل
        $existingPermissions = Permission::pluck('name')->toArray();

        // مصفوفة لتخزين جميع مفاتيح الصلاحيات التي سيتم إضافتها
        $permissionsToSeed = [];

        // المرور على كل كيان (entity) وكل فعل (action) داخل ملف الصلاحيات
        foreach ($permissionsConfig as $entity => $actions) {
            foreach ($actions as $key => $actionData) {
                // تجاهل عنصر 'name' لأنه ليس صلاحية
                if ($key === 'name')
                    continue;

                if (is_array($actionData) && isset($actionData['key'])) {
                    if (!in_array($actionData['key'], $existingPermissions)) {
                        $permissionsToSeed[] = [
                            'name' => $actionData['key'],
                            'guard_name' => 'web',
                            'created_at' => now(),
                            'updated_at' => now(),
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

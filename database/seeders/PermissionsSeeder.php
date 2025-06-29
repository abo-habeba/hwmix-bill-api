<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;  // تأكد من استخدام موديل الصلاحيات الصحيح

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // مسح الصلاحيات الموجودة مسبقًا لتجنب التكرار في كل مرة يتم تشغيل Seeder
        Permission::query()->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // جلب جميع تعريفات الصلاحيات من ملف config/permissions_keys.php
        $permissionsConfig = config('permissions_keys');

        // مصفوفة لتخزين جميع مفاتيح الصلاحيات التي سيتم إضافتها
        $permissionsToSeed = [];

        // المرور على كل كيان (entity) وكل فعل (action) داخل ملف الصلاحيات
        foreach ($permissionsConfig as $entity => $actions) {
            foreach ($actions as $key => $actionData) {
                if ($key === 'name')
                    continue;
                // التأكد من أن المفتاح 'key' موجود لضمان عدم وجود أخطاء
                if (isset($actionData['key'])) {
                    $permissionsToSeed[] = [
                        'name' => $actionData['key'],
                        'guard_name' => 'web',
                        'created_at' => now(),  // إضافة timestamp
                        'updated_at' => now(),  // إضافة timestamp
                    ];
                }
            }
        }

        // إدراج جميع الصلاحيات دفعة واحدة في جدول الصلاحيات
        // هذا الأسلوب أسرع بكثير من الإدراج في حلقة
        Permission::insert($permissionsToSeed);

        // تشغيل Seeder الخاص بالأدوار والصلاحيات بعد الصلاحيات
        $this->call(RolesAndPermissionsSeeder::class);

        $this->command->info('Permissions seeded successfully!');
    }
}

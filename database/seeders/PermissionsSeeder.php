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

        // استخراج جميع قيم value من جميع ملفات json في مصفوفة واحدة
        $permissionsArray = [];
        $jsonDir = database_path('seeders/permission-json');
        foreach (glob($jsonDir . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            foreach ($data['permissions'] as $permission) {
                $permissionsArray[] = $permission['value'];
            }
        }

        // إضافة جميع الصلاحيات إلى جدول permissions في لارافيل
        $insertData = array_map(fn($permission) => [
            'name' => $permission,
            'guard_name' => 'web',
        ], $permissionsArray);

        \Spatie\Permission\Models\Permission::insert($insertData);
    }
}

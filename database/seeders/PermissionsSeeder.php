<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Company;
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
        // ✅ تنظيف كل أنواع الكاش (Application + Config + Route + View + Compiled)
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('route:clear');
        \Artisan::call('view:clear');
        \Artisan::call('clear-compiled');


        // مسح الصلاحيات الموجودة مسبقًا لتجنب التكرار في كل مرة يتم تشغيل Seeder
        Permission::query()->delete();

        // ✅ تنظيف كاش الصلاحيات الخاص بـ Spatie
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

        // تشغيل Seeder الخاص بالمستخدمين أولاً إذا كان ينشئ المستخدمين
        $this->call(RolesAndPermissionsSeeder::class);

        // جلب أول مستخدم موجود بعد تنفيذ Seeder
        $admin = User::first();
        $userId = $admin ? $admin->id : null;
        $this->command->info('قائمة الإيميلات الموجودة: ' . User::pluck('email')->implode(', '));
        if (!$userId) {
            $this->command->error('لا يوجد مستخدم في قاعدة البيانات بعد تنفيذ Seeder المستخدمين. الرجاء التأكد من منطق إنشاء المستخدم.');
            return;
        }

        // ----------- إنشاء الأدوار وتعيين الصلاحيات تلقائياً -----------
        $roleModel = app(Role::class);
        $roles = [
            [
                'name' => 'مدير شركة',
                'actions' => [
                    'change_active_company',
                    'page',
                    'view_all',
                    'view_children',
                    'view_self',
                    'create',
                    'update_all',
                    'update_children',
                    'update_self',
                    'delete_all',
                    'delete_children',
                    'delete_self',
                ],
            ],
            [
                'name' => 'ادارة جميع السجلات',
                'actions' => ['page', 'view_all', 'update_all', 'delete_all'],
            ],
            [
                'name' => 'ادارة سجلاته فقط',
                'actions' => ['page', 'view_self', 'update_self', 'delete_self'],
            ],
            [
                'name' => 'ادارة سجلاته وسجلات التابعين له',
                'actions' => ['page', 'view_children', 'update_children', 'delete_children'],
            ],
            [
                'name' => 'اضافة سجلات',
                'actions' => ['page', 'create'],
            ],

        ];
        $companyModel = app(Company::class);
        $firstCompany = $companyModel::first();
        $companyIdForRoles = $firstCompany ? $firstCompany->id : null;
        foreach ($roles as $roleData) {
            $role = $roleModel::firstOrCreate([
                'name' => $roleData['name'],
                'company_id' => $companyIdForRoles,
                'created_by' => $userId
            ], ['guard_name' => 'web']);
            $permissions = [];
            foreach ($permissionsConfig as $entity => $actions) {
                foreach ($actions as $key => $actionData) {
                    if ($key === 'name') continue;
                    if (isset($actionData['key']) && in_array($key, $roleData['actions'])) {
                        $permissions[] = $actionData['key'];
                    }
                }
            }
            $role->syncPermissions($permissions);
        }

        // ربط كل شركة بكل الأدوار الأربعة في جدول الشركة-الدور
        $companyRoleTable = 'role_company';
        $companies = $companyModel::all();
        $roleIds = $roleModel::whereIn('name', array_column($roles, 'name'))->pluck('id', 'name');
        $now = now();
        $pivotRows = [];
        foreach ($companies as $company) {
            foreach ($roles as $roleData) {
                $pivotRows[] = [
                    'company_id' => $company->id,
                    'role_id' => $roleIds[$roleData['name']],
                    'created_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        // حذف جميع البيانات من جدول الربط قبل الإدراج لضمان عدم وجود بيانات سابقة بقيم null
        \DB::table($companyRoleTable)->truncate();
        if (!empty($pivotRows)) {
            \DB::table($companyRoleTable)->insert($pivotRows);
        }
        $this->command->info('Roles and company-role relations seeded successfully!');
    }
}

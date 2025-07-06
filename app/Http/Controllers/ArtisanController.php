<?php

namespace App\Http\Controllers;

use Throwable;
use Illuminate\Http\JsonResponse;
use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\CashBox;
use App\Models\CashBoxType;
use Illuminate\Support\Facades\DB;

class ArtisanController extends Controller
{
    /**
     * تشغيل أمر composer dump-autoload.
     * @return \Illuminate\Http\JsonResponse
     * عمل اوتو لود للملفات 
     */
    public function runComposerDump(): JsonResponse
    {
        try {
            $output = shell_exec('composer2 dump-autoload 2>&1');
            // $output = shell_exec('composer dump-autoload 2>&1');
            return api_success(['output' => $output], 'تم تنفيذ أمر Composer dump-autoload بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * // ميجريشن ريفرش وعمل سيدرنج لقاعدة البيانات من جديد
     */
    public function migrateAndSeed(): JsonResponse
    {
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            $migrateOutput = Artisan::output(); // التقاط مخرجات الهجرة
            Artisan::call('db:seed', ['--force' => true]);
            $seedOutput = Artisan::output(); // التقاط مخرجات التغذية

            return api_success([
                'migrate_output' => $migrateOutput,
                'seed_output' => 'تم تنفيذ Seeders بنجاح.',
            ], 'تم ترحيل قاعدة البيانات وتغذيتها بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تشغيل RolesAndPermissionsSeeder.
     * @return \Illuminate\Http\JsonResponse
     */
    public function seedRolesAndPermissions(): JsonResponse
    {
        try {
            Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\RolesAndPermissionsSeeder',
                '--force' => true
            ]);
            return api_success([], 'تم تنفيذ RolesAndPermissionsSeeder بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تشغيل PermissionsSeeder (دالة مكررة، تم توحيدها).
     * @return \Illuminate\Http\JsonResponse
     */
    public function PermissionsSeeder(): JsonResponse
    {
        try {
            Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\PermissionsSeeder',
                '--force' => true
            ]);
            return api_success([], 'تم تنفيذ PermissionsSeeder بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }
    public function DatabaseSeeder(): JsonResponse
    {
        try {
            Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\DatabaseSeeder',
                '--force' => true
            ]);
            return api_success([], 'تم تنفيذ DatabaseSeeder بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * مسح جميع الكاشات وإعادة بنائها.
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearAllCache(): JsonResponse
    {
        try {
            // تنظيف الكاشات
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('clear-compiled');

            // تنظيف كاش Spatie لو موجود
            if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }

            // إعادة بناء الكاشات المهمة
            Artisan::call('config:cache');
            Artisan::call('route:cache');

            return api_success([
                'cache' => 'تم مسح الكاش',
                'config' => 'تم مسح وإعادة بناء كاش الإعدادات',
                'route' => 'تم مسح وإعادة بناء كاش المسارات',
                'view' => 'تم مسح كاش العروض',
                'compiled' => 'تم مسح الملفات المترجمة',
                'permissions' => 'تم مسح كاش صلاحيات Spatie (إذا كان موجودًا)',
            ], 'تم مسح جميع الكاشات وإعادة بنائها بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تصدير البيانات وتوليد السيدرز.
     */
    public function generateBackup(): JsonResponse
    {
        try {
            $service = new DatabaseBackupService();
            $report = $service->exportDataAndGenerateSeeders();

            $message = empty($report['errors']) ? 'تم اكتمال النسخ الاحتياطي بنجاح.' : 'تم اكتمال النسخ الاحتياطي مع بعض الأخطاء.';
            $status = empty($report['errors']) ? 'نجاح' : 'تحذير';

            return api_success($report, $message);
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    /**
     * تشغيل السيدرز التي تم توليدها.
     */
    public function applyBackup(): JsonResponse
    {
        try {
            $service = new DatabaseBackupService();
            $service->runBackupSeeders();

            return api_success([], 'تم تشغيل السيدرز الاحتياطية بنجاح.');
        } catch (Throwable $e) {
            return api_exception($e);
        }
    }

    public function ensureCashBoxesForAllUsers()
    {
        DB::transaction(function () {
            // 1. نحصل على النوع "نقدي"
            $cashType = CashBoxType::where('name', 'نقدي')->first();

            if (!$cashType) {
                throw new \Exception('نوع الخزنة "نقدي" غير موجود في جدول cash_box_types.');
            }

            // 2. لكل مستخدم: أنشئ له خزنة إن لم يكن لديه واحدة من هذا النوع
            User::with('cashBoxes')->get()->each(function ($user) use ($cashType) {
                $hasCashBox = $user->cashBoxes()->where('cash_box_type_id', $cashType->id)->exists();

                if (!$hasCashBox) {
                    CashBox::create([
                        'name' => 'الخزنة النقدية - ' . $user->nickname ?? $user->name,
                        'balance' => 0,
                        'cash_box_type_id' => $cashType->id,
                        'is_default' => true,
                        'user_id' => $user->id,
                        'created_by' => $user->id,
                        'company_id' => $user->company_id,
                        'description' => 'تم إنشاؤها تلقائيًا',
                        'account_number' => null,
                    ]);
                }
            });
        });
    }
}

<?php

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Database\Seeders\Backup\RunAllBackupSeeders;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Exception;

class DatabaseBackupController extends Controller
{
    // 1. تصدير البيانات وتوليد السيدرز
    public function export(): JsonResponse
    {
        try {
            $service = new DatabaseBackupService();
            // 1. تصدير البيانات وتوليد السيدرز
            $report = $service->exportDataAndGenerateSeeders();

            return response()->json([
                'status' => empty($report['errors']) ? '✅ Backup completed with no errors' : '⚠️ Backup completed with some errors',
                'details' => $report
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => '❌ Fatal Error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    //    // 2. تشغيل السيدرز التي تم توليدها
    public function runSeeders(): JsonResponse
    {
        try {
            $service = new DatabaseBackupService();
            $service->runBackupSeeders();

            return response()->json([
                'status' => '✅ Backup seeders ran successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => '❌ Error running seeders',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    // 3. عمل ريفرش للـمبجريشن واستعادة البيانات من السيدرز
    public function restoreAndFresh(): JsonResponse
    {
        try {
            // $service = new DatabaseBackupService();
            // 1. تصدير البيانات وتوليد السيدرز
            // $report = $service->exportDataAndGenerateSeeders();
            // if (!$report || !empty($report['errors'])) {
            //     return response()->json([
            //         'status' => '❌ Failed to export data and generate seeders',
            //         'details' => $report
            //     ], 500);
            // }

            // 2. عمل fresh للـ migrations
            $migrateResult = Artisan::call('migrate:fresh', ['--force' => true]);
            $migrateOutput = Artisan::output();

            if ($migrateResult !== 0) {
                return response()->json([
                    'status' => '❌ Failed to refresh migrations',
                    'migrate_output' => $migrateOutput,
                ], 500);
            }

            // 3. تشغيل السيدرز
            $seedResult = Artisan::call('db:seed', [
                '--class' => RunAllBackupSeeders::class,
                '--force' => true,
            ]);
            $seederOutput = Artisan::output();

            return response()->json([
                'status' => $seedResult === 0 ? '✅ Restore completed successfully' : '❌ Seeder execution failed',
                'migrate_output' => $migrateOutput,
                'seeder_output' => $seederOutput,
                'backup_steps' => $report['steps'] ?? [],
                'backup_errors' => $report['errors'] ?? [],
                'backup_seeders' => $report['seeders'] ?? [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => '❌ Error in restore/migrate',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}

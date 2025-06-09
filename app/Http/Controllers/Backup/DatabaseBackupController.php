<?php

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DatabaseBackupController extends Controller
{
    public function export(): JsonResponse
    {
        try {
            $service = new DatabaseBackupService();
            $classes = $service->exportDataAndGenerateSeeders();
            return response()->json(['status' => '✅ Backup & Seeders generated', 'seeders' => $classes]);
        } catch (Exception $e) {
            return response()->json(['status' => '❌ Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function runSeeders(): JsonResponse
    {
        try {
            $service = new DatabaseBackupService();
            $service->runBackupSeeders();
            return response()->json(['status' => '✅ Backup seeders ran successfully']);
        } catch (Exception $e) {
            return response()->json(['status' => '❌ Error running seeders', 'message' => $e->getMessage()], 500);
        }
    }

    public function restoreAndFresh(): JsonResponse
    {
        try {
            $service = new DatabaseBackupService();

            // الخطوة الأولى: تصدير البيانات وإنشاء ملفات السيدر (يشمل حذف الملفات القديمة)
            $exportResult = $service->exportDataAndGenerateSeeders();
            if (!$exportResult) {
                return response()->json(['status' => '❌ Failed to export data and generate seeders'], 500);
            }

            // الخطوة الثانية: عمل ريفريش للميجريشن
            $migrateResult = Artisan::call('migrate:fresh', ['--force' => true]);
            if ($migrateResult !== 0) {
                return response()->json(['status' => '❌ Failed to refresh migrations'], 500);
            }

            // الخطوة الثالثة: تشغيل جميع ملفات السيدر
            $seedResult = Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\Backup\RunAllBackupSeeders'
            ]);
            if ($seedResult !== 0) {
                return response()->json(['status' => '❌ Failed to run seeders'], 500);
            }

            return response()->json(['status' => '✅ Restore + Migrate Fresh + Seed All Done']);
        } catch (Exception $e) {
            return response()->json(['status' => '❌ Error in restore/migrate', 'message' => $e->getMessage()], 500);
        }
    }
}

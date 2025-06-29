<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class ArtisanController extends Controller
{
    /**
     * Run composer dump-autoload.
     * @return \Illuminate\Http\JsonResponse
     */
    public function dumpAutoload()
    {
        try {
            $output = shell_exec('composer dump-autoload');
            return response()->json([
                'status' => '✅ dump-autoload تم بنجاح',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => '❌ حصل خطأ',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Run migrate:fresh and db:seed (for development only).
     * @return \Illuminate\Http\JsonResponse
     */
    public function migrateAndSeed()
    {
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
            return response()->json([
                'migrate' => Artisan::output(),
                'seed' => 'Seeders executed successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Run PermissionsSeeder.
     * @return \Illuminate\Http\JsonResponse
     */
    public function seedPermissions()
    {
        try {
            Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\PermissionsSeeder',
                '--force' => true
            ]);
            return response()->json([
                'seed' => 'PermissionsSeeder executed successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Run RolesAndPermissionsSeeder.
     * @return \Illuminate\Http\JsonResponse
     */
    public function seedRolesAndPermissions()
    {
        try {
            Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\RolesAndPermissionsSeeder',
                '--force' => true
            ]);
            return response()->json([
                'seed' => 'RolesAndPermissionsSeeder executed successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    public function PermissionsSeeder()
    {
        try {
            Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\PermissionsSeeder',
                '--force' => true
            ]);
            return response()->json([
                'seed' => 'PermissionsSeeder executed successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

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

            return response()->json([
                'status' => '✅ All caches cleared and rebuilt successfully',
                'details' => [
                    'cache' => 'cleared',
                    'config' => 'cleared + cached',
                    'route' => 'cleared + cached',
                    'view' => 'cleared',
                    'compiled' => 'cleared',
                    'permissions' => 'spatie permissions cache cleared (if exists)',
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => '❌ Failed to clear and rebuild caches',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}

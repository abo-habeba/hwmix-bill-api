<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
}

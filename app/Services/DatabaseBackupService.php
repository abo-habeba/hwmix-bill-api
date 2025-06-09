<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Exception;

class DatabaseBackupService
{
    protected string $backupPath;

    public function __construct()
    {
        $this->backupPath = database_path('seeders/Backup');
        File::ensureDirectoryExists($this->backupPath);
    }

    public function exportDataAndGenerateSeeders(array $excludeTables = []): array
    {
        try {
            // حذف جميع ملفات الباك أب القديمة
            if (is_dir($this->backupPath)) {
                $files = glob($this->backupPath . '/*');  // الحصول على جميع الملفات في المجلد
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);  // حذف الملف
                    }
                }
            }

            $databaseName = DB::getDatabaseName();
            $tables = DB::select('SHOW TABLES');
            $key = "Tables_in_$databaseName";

            $seederClasses = [];
            $log = [];

            foreach ($tables as $table) {
                $tableName = $table->$key;

                // استثناء الجداول المحددة
                if (in_array($tableName, $excludeTables)) {
                    $log[] = "⚠️ تم استثناء الجدول: $tableName";
                    continue;
                }

                try {
                    // 1. جلب البيانات مع الاحتفاظ بـ IDs
                    $data = DB::table($tableName)->get();
                    if ($data->isEmpty()) {
                        $log[] = "⚠️ جدول '$tableName' فاضي، تم تخطيه.";
                        continue;
                    }

                    // 2. حفظ JSON
                    $jsonFile = "{$this->backupPath}/{$tableName}.json";
                    File::put($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                    if (!File::exists($jsonFile) || filesize($jsonFile) == 0) {
                        throw new Exception("❌ فشل في حفظ ملف JSON للجدول: $tableName");
                    }

                    // 3. توليد Seeder مع الاحتفاظ بـ IDs
                    $seederClass = Str::studly($tableName) . 'BackupSeeder';
                    $seederFile = "{$this->backupPath}/{$seederClass}.php";

                    $seederContent = $this->generateSeederContent($seederClass, $tableName);
                    File::put($seederFile, $seederContent);

                    if (!File::exists($seederFile) || filesize($seederFile) == 0) {
                        throw new Exception("❌ فشل في توليد Seeder لـ: $tableName");
                    }

                    // 4. التأكد من صلاحية JSON
                    $jsonCheck = json_decode(File::get($jsonFile), true);
                    if (!is_array($jsonCheck)) {
                        throw new Exception("❌ محتوى JSON غير صالح للجدول: $tableName");
                    }

                    $seederClasses[] = $seederClass;
                    $log[] = "✅ تم نسخ الجدول: $tableName";
                } catch (Exception $e) {
                    $log[] = "🛑 حصل خطأ في جدول '$tableName': " . $e->getMessage();
                }
            }

            $this->generateMasterSeeder($seederClasses);

            // حفظ السجل
            File::put("{$this->backupPath}/backup_log.txt", implode("\n", $log));

            return $seederClasses;
        } catch (Exception $e) {
            // تسجيل الخطأ إذا حدث
            File::put("{$this->backupPath}/backup_log.txt", $e->getMessage() . PHP_EOL, FILE_APPEND);
            return [];
        }
    }

    public function runBackupSeeders()
    {
        \Artisan::call('db:seed', [
            '--class' => 'Database\Seeders\Backup\RunAllBackupSeeders'
        ]);
    }

    protected function generateSeederContent($className, $tableName): string
    {
        return <<<PHP
            <?php

            namespace Database\Seeders\Backup;

            use Illuminate\Database\Seeder;
            use Illuminate\Support\Facades\DB;
            use Illuminate\Support\Facades\File;

            class {$className} extends Seeder
            {
                public function run()
                {
                    \$json = File::get(database_path('seeders/Backup/{$tableName}.json'));
                    \$data = json_decode(\$json, true);

                    foreach (\$data as \$row) {
                        if (!isset(\$row['id'])) {
                            // إذا لم يكن المفتاح 'id' موجودًا، يمكن تخطي السجل أو التعامل معه بطريقة أخرى
                            continue;
                        }
                        DB::table('{$tableName}')->updateOrInsert([
                            'id' => \$row['id']
                        ], \$row);
                    }
                }
            }
            PHP;
    }

    protected function generateMasterSeeder(array $classes): void
    {
        // قراءة ملفات الميجريشن وترتيب الجداول بناءً عليها
        $migrationFiles = File::files(database_path('migrations'));
        $migrationOrder = [];

        foreach ($migrationFiles as $file) {
            $fileName = $file->getFilename();
            if (preg_match('/create_(.*?)_table/', $fileName, $matches)) {
                $migrationOrder[] = Str::studly($matches[1]);
            }
        }

        // ترتيب Seeders بناءً على ترتيب الميجريشن
        usort($classes, function ($a, $b) use ($migrationOrder) {
            $indexA = array_search(str_replace('BackupSeeder', '', $a), $migrationOrder);
            $indexB = array_search(str_replace('BackupSeeder', '', $b), $migrationOrder);

            return $indexA <=> $indexB;
        });

        $lines = array_map(fn($class) => "        \$this->call({$class}::class);", $classes);
        $body = implode("\n", $lines);

        $content = <<<PHP
            <?php

            namespace Database\Seeders\Backup;

            use Illuminate\Database\Seeder;

            class RunAllBackupSeeders extends Seeder
            {
                public function run()
                {
            {$body}
                }
            }
            PHP;

        File::put("{$this->backupPath}/RunAllBackupSeeders.php", $content);
    }

    public function compressBackup(): void
    {
        $zipFile = "{$this->backupPath}/backup.zip";
        $zip = new \ZipArchive();

        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $files = File::files($this->backupPath);

            foreach ($files as $file) {
                if ($file->getExtension() === 'json' || $file->getExtension() === 'php') {
                    $zip->addFile($file->getRealPath(), $file->getFilename());
                }
            }

            $zip->close();
        }
    }
}

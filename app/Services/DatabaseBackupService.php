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
                $files = glob($this->backupPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
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

                if (in_array($tableName, $excludeTables)) {
                    $log[] = "⚠️ تم استثناء الجدول: $tableName";
                    continue;
                }

                try {
                    $data = DB::table($tableName)->get();
                    if ($data->isEmpty()) {
                        $log[] = "⚠️ جدول '$tableName' فاضي، تم تخطيه.";
                        continue;
                    }

                    $jsonFile = "{$this->backupPath}/{$tableName}.json";
                    File::put($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                    if (!File::exists($jsonFile) || filesize($jsonFile) == 0) {
                        throw new Exception("❌ فشل في حفظ ملف JSON للجدول: $tableName");
                    }

                    $seederClass = Str::studly($tableName) . 'BackupSeeder';
                    $seederFile = "{$this->backupPath}/{$seederClass}.php";

                    $primaryKeys = $this->getTablePrimaryKeys($tableName);

                    $seederContent = $this->generateSeederContent($seederClass, $tableName, $primaryKeys);
                    File::put($seederFile, $seederContent);

                    if (!File::exists($seederFile) || filesize($seederFile) == 0) {
                        throw new Exception("❌ فشل في توليد Seeder لـ: $tableName");
                    }

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

            File::put("{$this->backupPath}/backup_log.txt", implode("\n", $log));

            return $seederClasses;
        } catch (Exception $e) {
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

    protected function getTablePrimaryKeys(string $tableName): array
    {
        // جلب أسماء الأعمدة المفتاحية (Primary Key) للجدول
        $databaseName = DB::getDatabaseName();

        $results = DB::select("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? 
              AND TABLE_NAME = ? 
              AND CONSTRAINT_NAME = 'PRIMARY'
        ", [$databaseName, $tableName]);

        return array_map(fn($row) => $row->COLUMN_NAME, $results);
    }

    protected function generateSeederContent(string $className, string $tableName, array $primaryKeys): string
    {
        // إذا مفيش primary key، نستعمل insert فقط
        if (empty($primaryKeys)) {
            $condition = "null"; // معناه insert بدون updateOrInsert
        } else {
            // نستخدم primary keys كشرط تحديث
            $keysArray = "[\n";
            foreach ($primaryKeys as $key) {
                $keysArray .= "                    '{$key}' => \$row['{$key}'],\n";
            }
            $keysArray .= "                ]";
            $condition = $keysArray;
        }

        $updateOrInsertCode = $condition === "null"
            ? "DB::table('{$tableName}')->insert(\$row);"
            : "DB::table('{$tableName}')->updateOrInsert({$condition}, \$row);";

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
            // إذا البيانات فارغة نتخطى
            if (empty(\$row)) continue;

            {$updateOrInsertCode}
        }
    }
}

PHP;
    }

    protected function generateMasterSeeder(array $classes): void
    {
        $migrationFiles = File::files(database_path('migrations'));
        $migrationOrder = [];

        foreach ($migrationFiles as $file) {
            $fileName = $file->getFilename();
            if (preg_match('/create_(.*?)_table/', $fileName, $matches)) {
                $migrationOrder[] = Str::studly($matches[1]);
            }
        }

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
                if (in_array($file->getExtension(), ['json', 'php'])) {
                    $zip->addFile($file->getRealPath(), $file->getFilename());
                }
            }

            $zip->close();
        }
    }
}

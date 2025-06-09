<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Exception;
use ZipArchive;

class DatabaseBackupService
{
    protected string $backupPath;
    protected array $pivotTables = [];

    public function __construct()
    {
        $this->backupPath = database_path('seeders/Backup');
        File::ensureDirectoryExists($this->backupPath);
    }

    public function exportDataAndGenerateSeeders(array $excludeTables = []): array
    {
        try {
            // حذف ملفات النسخ الاحتياطي القديمة
            if (is_dir($this->backupPath)) {
                $files = glob($this->backupPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            $databaseName = DB::getDatabaseName();
            $tablesObj = DB::select('SHOW TABLES');
            $key = "Tables_in_$databaseName";
            $tables = array_map(fn($t) => $t->$key, $tablesObj);
            $excludeTables = array_merge($excludeTables, ['migrations']);
            $this->pivotTables = $this->detectPivotTables($tables);

            $seederClasses = [];
            $log = [];

            foreach ($tables as $tableName) {
                if (in_array($tableName, $excludeTables)) {
                    $log[] = "⚠️ تم استثناء الجدول: $tableName";
                    continue;
                }

                try {
                    $data = DB::table($tableName)->get();
                    if ($data->isEmpty()) {
                        $log[] = "⚠️ جدول '$tableName' فارغ، تم تخطيه.";
                        continue;
                    }

                    if (in_array($tableName, $this->pivotTables)) {
                        $data = $data->map(function ($row) {
                            $arr = (array) $row;
                            unset($arr['id']);
                            return $arr;
                        });
                    }

                    $jsonFile = "$this->backupPath/{$tableName}.json";
                    File::put($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                    if (!File::exists($jsonFile) || filesize($jsonFile) === 0) {
                        throw new Exception("❌ فشل في حفظ ملف JSON للجدول: $tableName");
                    }

                    $seederClass = Str::studly($tableName) . 'BackupSeeder';
                    $seederFile = "$this->backupPath/{$seederClass}.php";
                    $primaryKeys = $this->getTablePrimaryKeys($tableName);
                    $seederContent = $this->generateSeederContent($seederClass, $tableName, $primaryKeys);
                    File::put($seederFile, $seederContent);

                    if (!File::exists($seederFile) || filesize($seederFile) === 0) {
                        throw new Exception("❌ فشل في توليد Seeder لـ: $tableName");
                    }

                    $seederClasses[] = $seederClass;
                    $log[] = "✅ تم نسخ الجدول: $tableName";
                } catch (Exception $e) {
                    $log[] = "🛑 حصل خطأ في جدول '$tableName': " . $e->getMessage();
                }
            }

            $this->generateMasterSeeder($seederClasses);
            File::put("$this->backupPath/backup_log.txt", implode("\n", $log));

            return $seederClasses;
        } catch (Exception $e) {
            File::put("$this->backupPath/backup_log.txt", $e->getMessage() . PHP_EOL, FILE_APPEND);
            return [];
        }
    }

    public function runBackupSeeders()
    {
        Artisan::call('db:seed', [
            '--class' => 'Database\Seeders\Backup\RunAllBackupSeeders'
        ]);
    }

    protected function getTablePrimaryKeys(string $tableName): array
    {
        $databaseName = DB::getDatabaseName();

        $results = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'", [$databaseName, $tableName]);

        return array_map(fn($row) => $row->COLUMN_NAME, $results);
    }

    protected function generateSeederContent(string $className, string $tableName, array $primaryKeys): string
    {
        if (empty($primaryKeys)) {
            $condition = 'null';
            $updateOrInsertCode = "DB::table('{$tableName}')->insert(\$row);";
        } else {
            $condition = "[\n"
                . implode('', array_map(fn($key) => "                    '{$key}' => \$row['{$key}'],\n", $primaryKeys))
                . '                ]';

            $updateOrInsertCode = "DB::table('{$tableName}')->updateOrInsert({$condition}, \$row);";
        }

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
            $aName = str_replace('BackupSeeder', '', $a);
            $bName = str_replace('BackupSeeder', '', $b);

            $indexA = array_search($aName, $migrationOrder);
            $indexB = array_search($bName, $migrationOrder);

            if ($indexA === false)
                $indexA = PHP_INT_MAX;
            if ($indexB === false)
                $indexB = PHP_INT_MAX;

            // ترتيب يدوي للثنائي Permissions و ModelHasPermissions لو من نفس الميجريشن
            if ($indexA === $indexB) {
                $orderSpecial = [
                    'Permissions' => 1,
                    'ModelHasPermissions' => 2,
                ];

                $orderA = $orderSpecial[$aName] ?? 99;
                $orderB = $orderSpecial[$bName] ?? 99;

                return $orderA <=> $orderB;
            }

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

        File::put("$this->backupPath/RunAllBackupSeeders.php", $content);
    }

    public function compressBackup(): void
    {
        $zipFile = "$this->backupPath/backup.zip";
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = File::files($this->backupPath);

            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['json', 'php'])) {
                    $zip->addFile($file->getRealPath(), $file->getFilename());
                }
            }

            $zip->close();
        }
    }

    protected function detectPivotTables(array $tables): array
    {
        $pivotTables = [];

        foreach ($tables as $table) {
            $primaryKeys = $this->getTablePrimaryKeys($table);

            if (empty($primaryKeys) || (Str::contains($table, '_') && $this->hasOnlyForeignKeys($table))) {
                $pivotTables[] = $table;
            }
        }

        return $pivotTables;
    }

    protected function hasOnlyForeignKeys(string $table): bool
    {
        $columns = DB::getSchemaBuilder()->getColumnListing($table);

        foreach ($columns as $column) {
            if (!Str::endsWith($column, '_id') && !in_array($column, ['created_at', 'updated_at'])) {
                return false;
            }
        }

        return true;
    }
}

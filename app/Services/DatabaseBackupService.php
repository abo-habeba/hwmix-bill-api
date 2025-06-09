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
        $report = ['steps' => [], 'errors' => [], 'seeders' => []];

        try {
            $report['steps'][] = '🚀 بدء عملية النسخ الاحتياطي وتوليد seeders';

            // حذف الملفات القديمة
            try {
                if (is_dir($this->backupPath)) {
                    foreach (glob($this->backupPath . '/*') as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                    $report['steps'][] = '🧹 تم حذف النسخ القديمة بنجاح';
                }
            } catch (Exception $e) {
                $report['errors'][] = '❌ فشل في حذف الملفات القديمة: ' . $e->getMessage();
            }

            $databaseName = DB::getDatabaseName();
            $tablesObj = DB::select('SHOW TABLES');
            $key = "Tables_in_$databaseName";
            $tables = array_map(fn($t) => $t->$key, $tablesObj);

            $excludeTables = array_merge($excludeTables, ['migrations']);
            $this->pivotTables = $this->detectPivotTables($tables);

            $seederClasses = [];

            foreach ($tables as $tableName) {
                if (in_array($tableName, $excludeTables)) {
                    $report['steps'][] = "⏭️ تم استثناء الجدول: $tableName";
                    continue;
                }

                try {
                    $report['steps'][] = "📦 جاري تصدير الجدول: $tableName";

                    $data = DB::table($tableName)->get();
                    if ($data->isEmpty()) {
                        $report['steps'][] = "⚠️ جدول '$tableName' فارغ، تم تخطيه.";
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
                        throw new Exception("لم يتم إنشاء ملف JSON للجدول: $tableName");
                    }

                    $seederClass = Str::studly($tableName) . 'BackupSeeder';
                    $seederFile = "$this->backupPath/{$seederClass}.php";
                    $primaryKeys = $this->getTablePrimaryKeys($tableName);
                    $seederContent = $this->generateSeederContent($seederClass, $tableName, $primaryKeys);
                    File::put($seederFile, $seederContent);

                    if (!File::exists($seederFile) || filesize($seederFile) === 0) {
                        throw new Exception("فشل في توليد Seeder لجدول: $tableName");
                    }

                    $report['steps'][] = "✅ تم توليد Seeder لجدول: $tableName";
                    $seederClasses[] = $seederClass;
                } catch (Exception $e) {
                    $report['errors'][] = "🛑 خطأ أثناء تصدير الجدول '$tableName': " . $e->getMessage();
                }
            }

            $this->generateMasterSeeder($seederClasses);
            $report['steps'][] = '✅ تم توليد Seeder الرئيسي RunAllBackupSeeders.php';

            $report['seeders'] = $seederClasses;

            File::put("$this->backupPath/backup_log.txt", implode("\n", array_merge($report['steps'], $report['errors'])));

            return $report;
        } catch (Exception $e) {
            $report['errors'][] = '❌ خطأ عام: ' . $e->getMessage();
            File::put("$this->backupPath/backup_log.txt", implode("\n", $report['errors']));
            return $report;
        }
    }

    public function runBackupSeeders()
    {
        Artisan::call('db:seed', [
            '--class' => 'Database\Seeders\Backup\RunAllBackupSeeders',
            '--force' => true
        ]);
    }

    protected function getTablePrimaryKeys(string $tableName): array
    {
        $dbName = DB::getDatabaseName();
        $results = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'", [$dbName, $tableName]);
        return array_map(fn($row) => $row->COLUMN_NAME, $results);
    }

    protected function generateSeederContent(string $className, string $tableName, array $primaryKeys): string
    {
        $condition = empty($primaryKeys)
            ? 'null'
            : "[\n" . implode('', array_map(fn($key) => "            '{$key}' => \$row['{$key}'],\n", $primaryKeys)) . '        ]';

        $code = empty($primaryKeys)
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
                        if (empty(\$row)) continue;

                        {$code}
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
            if (preg_match('/create_(.*?)_table/', $file->getFilename(), $matches)) {
                $migrationOrder[] = Str::studly($matches[1]);
            }
        }

        usort($classes, function ($a, $b) use ($migrationOrder) {
            $aName = str_replace('BackupSeeder', '', $a);
            $bName = str_replace('BackupSeeder', '', $b);

            $indexA = array_search($aName, $migrationOrder) ?: PHP_INT_MAX;
            $indexB = array_search($bName, $migrationOrder) ?: PHP_INT_MAX;

            if ($indexA === $indexB) {
                $order = ['Permissions' => 1, 'ModelHasPermissions' => 2];
                return ($order[$aName] ?? 99) <=> ($order[$bName] ?? 99);
            }

            return $indexA <=> $indexB;
        });

        $body = implode("\n", array_map(fn($class) => "        \$this->call({$class}::class);", $classes));

        File::put("$this->backupPath/RunAllBackupSeeders.php", <<<PHP
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
            PHP);
    }

    protected function detectPivotTables(array $tables): array
    {
        return array_filter($tables, fn($table) =>
            empty($this->getTablePrimaryKeys($table)) ||
            (Str::contains($table, '_') && $this->hasOnlyForeignKeys($table)));
    }

    protected function hasOnlyForeignKeys(string $table): bool
    {
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        return collect($columns)->every(fn($col) => Str::endsWith($col, '_id') || in_array($col, ['created_at', 'updated_at']));
    }
}

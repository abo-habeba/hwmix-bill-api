<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Exception;

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
            try {
                if (is_dir($this->backupPath)) {
                    foreach (File::files($this->backupPath) as $file) {
                        if (in_array($file->getExtension(), ['php', 'json'])) {
                            unlink($file->getRealPath());
                        }
                    }
                    $report['steps'][] = '🧹 تم حذف النسخ القديمة بنجاح';
                }
            } catch (Exception $e) {
                $report['errors'][] = '❌ فشل في حذف الملفات القديمة: ' . $e->getMessage();
            }

            $databaseName = DB::getDatabaseName();
            $tablesObj = DB::select('SHOW TABLES');
            $key = "Tables_in_{$databaseName}";
            $allTables = array_map(fn($t) => $t->$key, $tablesObj);

            $excludeTables = array_merge($excludeTables, ['migrations']);
            $this->pivotTables = $this->detectPivotTables($allTables);

            // Get the ordered table names from migrations first (for general ordering)
            $migrationOrderedTables = $this->getMigrationTablesOrder();
            $seederClassesToGenerate = [];

            // Prepare a map to get the migration index for each table
            $migrationTableIndexMap = [];
            foreach ($migrationOrderedTables as $index => $tableName) {
                $migrationTableIndexMap[strtolower($tableName)] = $index + 1;  // Start from 1, use lowercase for consistent lookup
            }

            // Define explicit priorities for critical tables to ensure correct seeding order
            // Companies must come first as many other tables (like users) depend on it.
            $priorityTables = [
                'companies' => 1,  // Companies should be seeded first
                'permissions' => 2,
                     'users' => 3,  // Users should be seeded after companies, permissions, and roles
                'roles' => 4,
             'role_has_permissions' => 5,  // Depends on roles and permissions
                'model_has_roles' => 6,  // Depends on roles and models (users)
                'model_has_permissions' => 7,  // Depends on permissions and models (users)
            ];

            // To ensure unique numbers for non-priority tables,
            // we'll assign a starting index after the highest priority number.
            $nextGeneralIndex = max(array_values($priorityTables)) + 1;

            foreach ($allTables as $tableName) {
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
                    $jsonFile = "{$this->backupPath}/{$tableName}.json";
                    File::put($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    if (!File::exists($jsonFile) || filesize($jsonFile) === 0) {
                        throw new Exception("لم يتم إنشاء ملف JSON للجدول: $tableName");
                    }

                    // Determine the numerical prefix for the seeder file and class name
                    $numericPrefix = '';
                    $lowerTableName = strtolower($tableName);

                    if (isset($priorityTables[$lowerTableName])) {
                        $numericPrefix = sprintf('%03d', $priorityTables[$lowerTableName]);
                    } else {
                        // For non-priority tables, use migration order if available, otherwise a high number
                        // and ensure it's unique by using and incrementing nextGeneralIndex
                        $baseIndex = $migrationTableIndexMap[$lowerTableName] ?? 999;

                        // If the base index from migration order is smaller than or equal to our highest priority,
                        // assign it the next available general index to avoid conflicts.
                        if ($baseIndex <= max(array_values($priorityTables))) {
                            $numericPrefix = sprintf('%03d', $nextGeneralIndex++);
                        } else {
                            $numericPrefix = sprintf('%03d', $baseIndex);
                        }
                    }

                    $fullPrefix = 'N' . $numericPrefix;  // Prefix format: N001, N002, etc.

                    // The seeder class name now includes the new prefix format
                    $seederClassName = $fullPrefix . '_' . Str::studly($tableName) . 'BackupSeeder';

                    // The seeder file name also includes the new prefix format
                    $seederFile = "{$this->backupPath}/{$seederClassName}.php";

                    $primaryKeys = $this->getTablePrimaryKeys($tableName);

                    // Pass the full class name (with new prefix) to generateSeederContent
                    $seederContent = $this->generateSeederContent($seederClassName, $tableName, $primaryKeys);
                    File::put($seederFile, $seederContent);
                    if (!File::exists($seederFile) || filesize($seederFile) === 0) {
                        throw new Exception("فشل في توليد Seeder لجدول: $tableName");
                    }
                    $report['steps'][] = "✅ تم توليد Seeder لجدول: $tableName";
                    $seederClassesToGenerate[] = $seederClassName;  // Store the full seeder class name
                } catch (Exception $e) {
                    $report['errors'][] = "🛑 خطأ أثناء تصدير الجدول '$tableName': " . $e->getMessage();
                }
            }

            // Now, when generating the master seeder, it will simply read and sort by prefix
            $this->generateMasterSeeder($seederClassesToGenerate);
            $report['steps'][] = '✅ تم توليد Seeder الرئيسي RunAllBackupSeeders.php';
            $report['seeders'] = $seederClassesToGenerate;
            File::put("{$this->backupPath}/backup_log.txt", implode("\n", array_merge($report['steps'], $report['errors'])));
            return $report;
        } catch (Exception $e) {
            $report['errors'][] = '❌ خطأ عام: ' . $e->getMessage();
            File::put("{$this->backupPath}/backup_log.txt", implode("\n", $report['errors']));
            return $report;
        }
    }

    public function runBackupSeeders(): array
    {
        try {
            $exitCode = Artisan::call('db:seed', [
                '--class' => 'Database\Seeders\Backup\RunAllBackupSeeders',
                '--force' => true,
            ]);
            if ($exitCode !== 0) {
                return ['success' => false, 'message' => 'فشل في استيراد البيانات. كود الخطأ: ' . $exitCode];
            }
            return ['success' => true, 'message' => 'تم استيراد البيانات بنجاح'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطأ أثناء استيراد البيانات: ' . $e->getMessage()];
        }
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
            : "[\n" . implode('', array_map(fn($key) => "                '$key' => \$row['$key'],\n", $primaryKeys)) . '            ]';

        $code = empty($primaryKeys)
            ? "DB::table('$tableName')->insert(\$row);"
            : "DB::table('$tableName')->updateOrInsert($condition, \$row);";

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
        $seederFiles = File::files($this->backupPath);
        $seederClassNames = [];

        foreach ($seederFiles as $file) {
            $fileName = $file->getFilenameWithoutExtension();
            if (preg_match('/^N\d{3}_(.+)BackupSeeder$/', $fileName) && $fileName !== 'RunAllBackupSeeders') {
                $seederClassNames[] = $fileName;
            }
        }

        sort($seederClassNames);  // Sorts by N prefix then number

        $body = implode("\n", array_map(fn($className) => "        \$this->call({$className}::class);", $seederClassNames));

        File::put("{$this->backupPath}/RunAllBackupSeeders.php", <<<PHP
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

    protected function getMigrationTablesOrder(): array
    {
        // This function's primary role is now to get a baseline order,
        // but explicit priority numbers in exportDataAndGenerateSeeders will override this for specific tables.
        $files = File::files(database_path('migrations'));

        $createTableMigrations = array_filter($files, function ($file) {
            return preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_create_(.+)_table\.php$/', $file->getFilename());
        });

        usort($createTableMigrations, function ($a, $b) {
            return strcmp($a->getFilename(), $b->getFilename());
        });

        $orderedTables = [];
        foreach ($createTableMigrations as $file) {
            preg_match('/create_(.+)_table\.php$/', $file->getFilename(), $matches);
            if (isset($matches[1])) {
                $orderedTables[] = strtolower($matches[1]);
            }
        }
        return $orderedTables;
    }

    protected function detectPivotTables(array $tables): array
    {
        $pivotTables = [];
        foreach ($tables as $table) {
            $primaryKeys = $this->getTablePrimaryKeys($table);
            if (empty($primaryKeys) || (count($primaryKeys) > 1 && $this->areAllForeignKeys($table, $primaryKeys))) {
                $pivotTables[] = $table;
            }
        }
        return $pivotTables;
    }

    protected function areAllForeignKeys(string $tableName, array $primaryKeys): bool
    {
        $dbName = DB::getDatabaseName();
        foreach ($primaryKeys as $key) {
            $results = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$dbName, $tableName, $key]);
            if (empty($results)) {
                return false;
            }
        }
        return true;
    }
}

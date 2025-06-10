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

            // Get the ordered table names from migrations first
            $migrationOrderedTables = $this->getMigrationTablesOrder();
            $seederClassesToGenerate = [];

            // Prepare a map to get the migration index for each table
            $migrationTableIndexMap = [];
            foreach ($migrationOrderedTables as $index => $tableName) {
                $migrationTableIndexMap[strtolower($tableName)] = $index + 1;  // Start from 1, use lowercase for consistent lookup
            }

            // Define explicit priorities for tables that must be at the END of the seeding process
            // Assign high numbers to ensure they appear last when sorted.
            $endPriorityTables = [
                'permissions' => 997,
                'roles' => 998,
                'role_has_permissions' => 999,
            ];

            // To ensure unique numbers for non-priority tables,
            // we'll assign a starting index for general tables that avoids conflict with endPriorityTables.
            // Start general indices from 1 and let them increment normally.
            $nextGeneralIndex = 1;

            foreach ($allTables as $tableName) {
                if (in_array($tableName, $excludeTables)) {
                    $report['steps'][] = "⏭️ تم استثناء الجدول: $tableName";
                    continue;
                }
                try {
                    $report['steps'][] = "📦 جاري تصدير الجدول: $tableName";
                    $data = DB::table($tableName)->get();

                    // --- التعديل هنا: إزالة شرط التخطي للجداول الفارغة ---
                    // if ($data->isEmpty()) {
                    //     $report['steps'][] = "⚠️ جدول '$tableName' فارغ، تم تخطيه.";
                    //     continue;
                    // }
                    // --------------------------------------------------

                    if (in_array($tableName, $this->pivotTables)) {
                        $data = $data->map(function ($row) {
                            $arr = (array) $row;
                            unset($arr['id']);
                            return $arr;
                        });
                    }
                    $jsonFile = "{$this->backupPath}/{$tableName}.json";
                    // نكتب بيانات فارغة في ملف JSON إذا كان الجدول فارغاً
                    File::put($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    if (!File::exists($jsonFile) || filesize($jsonFile) === 0) {
                        // لا نعتبره خطأ إذا كان الملف موجوداً وفارغاً (لأن الجدول قد يكون فارغاً)
                        // يمكننا التمييز بين عدم الإنشاء والإنشاء الفارغ
                        if (!File::exists($jsonFile)) {
                            throw new Exception("لم يتم إنشاء ملف JSON للجدول: $tableName");
                        }
                    }

                    // Determine the numerical prefix for the seeder file and class name
                    $numericPrefix = '';
                    $lowerTableName = strtolower($tableName);

                    if (isset($endPriorityTables[$lowerTableName])) {
                        // Assign the high priority number for tables that should be at the end
                        $numericPrefix = sprintf('%03d', $endPriorityTables[$lowerTableName]);
                    } else {
                        // For all other tables, try to use migration order.
                        // If no migration order, use a high default number, but ensure it's not conflicting
                        // with our specific end-priority tables (997-999).
                        $baseIndex = $migrationTableIndexMap[$lowerTableName] ?? null;

                        if ($baseIndex !== null && $baseIndex < min(array_values($endPriorityTables))) {
                            // If migration order exists and is less than our end priorities, use it
                            $numericPrefix = sprintf('%03d', $baseIndex);
                        } else {
                            // Otherwise, assign a general incrementing number that won't conflict
                            // with the end-priority tables. Start from 1 and increment.
                            $numericPrefix = sprintf('%03d', $nextGeneralIndex++);
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
                    // التحقق من أن البيانات ليست فارغة قبل محاولة الإدخال
                    if (!empty(\$data)) {
                        foreach (\$data as \$row) {
                            if (empty(\$row)) continue;
                            {$code}
                        }
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
        // This function's primary role is now to get a baseline order for dynamic tables.
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

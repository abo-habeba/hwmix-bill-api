<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Exception;

class DatabaseBackupService
{
    protected string $backupBasePath;
    protected string $jsonBackupPath;
    protected string $seedersBackupPath;
    protected array $pivotTables = [];

    public function __construct()
    {
        $this->backupBasePath = database_path('seeders/Backup');
        $this->jsonBackupPath = $this->backupBasePath . '/json_data';
        $this->seedersBackupPath = $this->backupBasePath . '/seeders';

        // التأكد من وجود المسارات المطلوبة
        File::ensureDirectoryExists($this->jsonBackupPath);
        File::ensureDirectoryExists($this->seedersBackupPath);
    }

    public function exportDataAndGenerateSeeders(array $excludeTables = []): array
    {
        $report = ['steps' => [], 'errors' => [], 'seeders' => []];

        try {
            $report['steps'][] = '🚀 بدء عملية النسخ الاحتياطي وتوليد seeders';

            // حذف الملفات القديمة من كلا المجلدين
            $this->cleanOldBackupFiles($report);

            $databaseName = DB::getDatabaseName();
            $allTables = $this->getAllTableNames($databaseName);

            $excludeTables = array_merge($excludeTables, ['migrations']);
            $this->pivotTables = $this->detectPivotTables($allTables);

            $migrationOrderedTables = $this->getMigrationTablesOrder();
            $seederClassesToGenerate = [];

            $migrationTableIndexMap = [];
            foreach ($migrationOrderedTables as $index => $tableName) {
                $migrationTableIndexMap[strtolower($tableName)] = $index + 1;
            }

            $endPriorityTables = [
                'permissions' => 994,
                'roles' => 995,
                'role_has_permissions' => 996,
                'model_has_roles' => 997,
                'model_has_permissions' => 998,
            ];

            $minEndPriority = !empty($endPriorityTables) ? min(array_values($endPriorityTables)) : PHP_INT_MAX;
            $nextGeneralIndex = 1;

            foreach ($allTables as $tableName) {
                if (in_array($tableName, $excludeTables)) {
                    $report['steps'][] = "⏭️ تم استثناء الجدول: $tableName";
                    continue;
                }

                try {
                    $report['steps'][] = "📦 جاري تصدير الجدول: $tableName";
                    $data = DB::table($tableName)->get();

                    if (in_array($tableName, $this->pivotTables)) {
                        $data = $data->map(function ($row) {
                            $arr = (array) $row;
                            unset($arr['id']);  // إزالة الـ ID فقط للجداول الوسيطة
                            return $arr;
                        });
                    }

                    $jsonFile = "{$this->jsonBackupPath}/{$tableName}.json";
                    File::put($jsonFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                    if (!File::exists($jsonFile) || filesize($jsonFile) === 0) {
                        // لا نعتبره خطأ إذا كان الملف موجوداً وفارغاً (لأن الجدول قد يكون فارغاً)
                        if (!File::exists($jsonFile)) {
                            throw new Exception("لم يتم إنشاء ملف JSON للجدول: $tableName");
                        }
                    }

                    $numericPrefix = '';
                    $lowerTableName = strtolower($tableName);

                    if (isset($endPriorityTables[$lowerTableName])) {
                        $numericPrefix = sprintf('%03d', $endPriorityTables[$lowerTableName]);
                    } else {
                        $baseIndex = $migrationTableIndexMap[$lowerTableName] ?? null;
                        if ($baseIndex !== null && $baseIndex < $minEndPriority) {
                            $numericPrefix = sprintf('%03d', $baseIndex);
                        } else {
                            $numericPrefix = sprintf('%03d', $nextGeneralIndex++);
                        }
                    }

                    $fullPrefix = 'N' . $numericPrefix;
                    $seederClassName = $fullPrefix . '_' . Str::studly($tableName) . 'BackupSeeder';
                    $seederFile = "{$this->seedersBackupPath}/{$seederClassName}.php";

                    $primaryKeys = $this->getTablePrimaryKeys($tableName);

                    $seederContent = $this->generateSeederContent($seederClassName, $tableName, $primaryKeys);
                    File::put($seederFile, $seederContent);

                    if (!File::exists($seederFile) || filesize($seederFile) === 0) {
                        throw new Exception("فشل في توليد Seeder لجدول: $tableName");
                    }

                    $report['steps'][] = "✅ تم توليد Seeder لجدول: $tableName";
                    $seederClassesToGenerate[] = $seederClassName;
                } catch (Exception $e) {
                    $report['errors'][] = "🛑 خطأ أثناء تصدير الجدول '$tableName': " . $e->getMessage();
                }
            }

            $this->generateMasterSeeder($seederClassesToGenerate);
            $report['steps'][] = '✅ تم توليد Seeder الرئيسي RunAllBackupSeeders.php';
            $report['seeders'] = $seederClassesToGenerate;

            File::put("{$this->backupBasePath}/backup_log.txt", implode("\n", array_merge($report['steps'], $report['errors'])));
            return $report;
        } catch (Exception $e) {
            $report['errors'][] = '❌ خطأ عام: ' . $e->getMessage();
            File::put("{$this->backupBasePath}/backup_log.txt", implode("\n", $report['errors']));
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

    /**
     * يحذف جميع ملفات النسخ الاحتياطي القديمة (JSON و PHP seeders).
     */
    protected function cleanOldBackupFiles(array &$report): void
    {
        try {
            // حذف ملفات JSON القديمة
            foreach (File::files($this->jsonBackupPath) as $file) {
                if ($file->getExtension() === 'json') {
                    unlink($file->getRealPath());
                }
            }
            // حذف ملفات Seeder القديمة
            foreach (File::files($this->seedersBackupPath) as $file) {
                if ($file->getExtension() === 'php') {
                    unlink($file->getRealPath());
                }
            }
            // حذف ملف السجل القديم إن وجد
            if (File::exists("{$this->backupBasePath}/backup_log.txt")) {
                unlink("{$this->backupBasePath}/backup_log.txt");
            }
            $report['steps'][] = '🧹 تم حذف النسخ القديمة بنجاح';
        } catch (Exception $e) {
            $report['errors'][] = '❌ فشل في حذف الملفات القديمة: ' . $e->getMessage();
        }
    }

    /**
     * يجلب جميع أسماء الجداول من قاعدة البيانات.
     */
    protected function getAllTableNames(string $databaseName): array
    {
        $tablesObj = DB::select('SHOW TABLES');
        $key = "Tables_in_{$databaseName}";
        return array_map(fn($t) => $t->$key, $tablesObj);
    }

    /**
     * يجلب المفاتيح الأساسية لجدول معين.
     */
    protected function getTablePrimaryKeys(string $tableName): array
    {
        $dbName = DB::getDatabaseName();
        $results = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'", [$dbName, $tableName]);
        return array_map(fn($row) => $row->COLUMN_NAME, $results);
    }

    /**
     * يولد محتوى ملف Seeder لجدول معين.
     */
    protected function generateSeederContent(string $className, string $tableName, array $primaryKeys): string
    {
        $condition = empty($primaryKeys)
            ? 'null'
            : "[\n" . implode('', array_map(fn($key) => "                    '$key' => \$row['$key'],\n", $primaryKeys)) . '                ]';

        $code = empty($primaryKeys)
            ? "DB::table('$tableName')->insert(\$row);"
            : "DB::table('$tableName')->updateOrInsert($condition, \$row);";

        // يتم الآن قراءة ملف JSON من المسار الجديد
        return <<<PHP
            <?php

            namespace Database\Seeders\Backup\seeders;

            use Illuminate\Database\Seeder;
            use Illuminate\Support\Facades\DB;
            use Illuminate\Support\Facades\File;

            class {$className} extends Seeder
            {
                public function run()
                {
                    \$jsonPath = database_path('seeders/Backup/json_data/{$tableName}.json');
                    if (!File::exists(\$jsonPath)) {
                        \$this->command->warn("ملف البيانات {$tableName}.json غير موجود، تم تخطي السيدة.");
                        return;
                    }

                    \$json = File::get(\$jsonPath);
                    \$data = json_decode(\$json, true);

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

    /**
     * يولد ملف Seeder الرئيسي الذي يقوم بتشغيل جميع ملفات Seeders الأخرى.
     */
    protected function generateMasterSeeder(array $classes): void
    {
        $seederFiles = File::files($this->seedersBackupPath);  // البحث عن السيدرات في المجلد الجديد
        $seederClassNames = [];

        foreach ($seederFiles as $file) {
            $fileName = $file->getFilenameWithoutExtension();
            // التأكد من أن الملف ليس هو RunAllBackupSeeders نفسه
            if (preg_match('/^N\d{3}_(.+)BackupSeeder$/', $fileName)) {
                $seederClassNames[] = $fileName;
            }
        }

        sort($seederClassNames);  // الترتيب حسب البادئة الرقمية

        // *** التعديل هنا: تصحيح الـ namespace لاستدعاء السيدرات الفردية ***
        $body = implode("\n", array_map(fn($className) => "        \$this->call(\Database\Seeders\Backup\seeders\\{$className}::class);", $seederClassNames));

        // يتم إنشاء ملف Seeder الرئيسي مباشرة في مجلد Backup
        File::put("{$this->backupBasePath}/RunAllBackupSeeders.php", <<<PHP
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

    /**
     * يجلب ترتيب الجداول من ملفات الهجرة (Migrations).
     */
    protected function getMigrationTablesOrder(): array
    {
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

    /**
     * يكشف عن الجداول الوسيطة (Pivot Tables).
     */
    protected function detectPivotTables(array $tables): array
    {
        $pivotTables = [];
        foreach ($tables as $table) {
            $primaryKeys = $this->getTablePrimaryKeys($table);
            // تعتبر جداول Pivot إذا كانت لا تحتوي على مفتاح أساسي (مثل id تلقائي)
            // أو إذا كانت تحتوي على مفاتيح أساسية متعددة وجميعها مفاتيح خارجية (Foreign Keys).
            if (empty($primaryKeys) || (count($primaryKeys) > 1 && $this->areAllForeignKeys($table, $primaryKeys))) {
                $pivotTables[] = $table;
            }
        }
        return $pivotTables;
    }

    /**
     * يتحقق مما إذا كانت جميع المفاتيح الأساسية لجدول معين هي مفاتيح خارجية أيضًا.
     */
    protected function areAllForeignKeys(string $tableName, array $primaryKeys): bool
    {
        $dbName = DB::getDatabaseName();
        foreach ($primaryKeys as $key) {
            $results = DB::select('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$dbName, $tableName, $key]);
            if (empty($results)) {
                return false;  // إذا لم تكن أي من المفاتيح الأساسية مفتاحًا أجنبيًا، فليست جدولًا وسيطًا
            }
        }
        return true;
    }
}

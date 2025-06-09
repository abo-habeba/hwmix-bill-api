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

    /**
     * تصدير البيانات وتوليد السييدرز مع الترتيب الديناميكي وحذف الملفات القديمة بأمان.
     *
     * @param array $excludeTables
     * @return array تقرير عن خطوات العملية والأخطاء والـ seeders التي تم إنشاؤها
     */
    public function exportDataAndGenerateSeeders(array $excludeTables = []): array
    {
        $report = ['steps' => [], 'errors' => [], 'seeders' => []];

        try {
            $report['steps'][] = '🚀 بدء عملية النسخ الاحتياطي وتوليد seeders';

            // حذف ملفات JSON وPHP القديمة الخاصة بالنسخ الاحتياطي فقط
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
                            if (isset($arr['id'])) {
                                unset($arr['id']);
                            }
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

            // توليد Seeder رئيسي مرتب حسب الاعتماديات (FK)
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

    /**
     * تنفيذ السييدرز لإرجاع البيانات من النسخة الاحتياطية
     *
     * @return array حالة التنفيذ والرسائل
     */
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
     * جلب المفاتيح الأساسية للجدول
     */
    protected function getTablePrimaryKeys(string $tableName): array
    {
        $dbName = DB::getDatabaseName();
        $results = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'", [$dbName, $tableName]);
        return array_map(fn($row) => $row->COLUMN_NAME, $results);
    }

    /**
     * توليد محتوى Seeder لكل جدول
     */
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

    /**
     * توليد Seeder رئيسي مرتب حسب ترتيب اعتماديات foreign keys (ترتيب topological)
     */
    protected function generateMasterSeeder(array $classes): void
    {
        // استخراج أسماء الجداول فقط
        $tables = array_map(fn($class) => str_replace('BackupSeeder', '', $class), $classes);

        // بناء مصفوفة الاعتماديات
        $dependencies = [];
        foreach ($tables as $table) {
            $dependencies[$table] = $this->getForeignKeyDependencies($table);
        }

        // ترتيب الجداول حسب الاعتماديات (topological sort)
        try {
            $sortedTables = $this->topologicalSort($tables, $dependencies);
        } catch (Exception $e) {
            // في حالة وجود دورة، نترك الترتيب الأصلي مع تحذير
            $sortedTables = $tables;
        }

        // بناء مصفوفة السييدرز حسب الترتيب النهائي
        $sortedClasses = [];
        foreach ($sortedTables as $table) {
            $seeder = $table . 'BackupSeeder';
            if (in_array($seeder, $classes)) {
                $sortedClasses[] = $seeder;
            }
        }

        // وضع بعض السييدرز الخاصة في نهاية القائمة (لو موجودة)
        $manualOrder = ['PermissionsBackupSeeder', 'ModelHasPermissionsBackupSeeder'];
        foreach ($manualOrder as $specialSeeder) {
            if (($key = array_search($specialSeeder, $sortedClasses)) !== false) {
                unset($sortedClasses[$key]);
                $sortedClasses[] = $specialSeeder;
            }
        }

        $body = implode("\n", array_map(fn($class) => "        \$this->call({$class}::class);", $sortedClasses));

        File::put("$this->backupPath/RunAllBackupSeeders.php", <<<PHP
            <?php

            namespace Database\Seeders\Backup;

            use Illuminate\Database\Seeder;

            class RunAllBackupSeeders extends Seeder
            {
                public function run()
                {
            $body
                }
            }

            PHP);
    }

    /**
     * جلب جداول الاعتماديات (foreign keys) للجدول المحدد
     */
    protected function getForeignKeyDependencies(string $table): array
    {
        $dbName = DB::getDatabaseName();
        $results = DB::select('
            SELECT REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$dbName, $table]);

        return array_map(fn($row) => $row->REFERENCED_TABLE_NAME, $results);
    }

    /**
     * ترتيب topological sort لضمان ترتيب الجداول حسب الاعتماديات بدون حلقات
     */
    protected function topologicalSort(array $nodes, array $edges): array
    {
        $sorted = [];
        $visited = [];

        $visit = function ($node) use (&$visit, &$sorted, &$visited, $edges) {
            if (isset($visited[$node])) {
                if ($visited[$node] === 'temp') {
                    throw new Exception("تم اكتشاف دورة في الاعتماديات عند الجدول: $node");
                }
                return;
            }
            $visited[$node] = 'temp';
            foreach ($edges[$node] ?? [] as $m) {
                $visit($m);
            }
            $visited[$node] = 'perm';
            $sorted[] = $node;
        };

        foreach ($nodes as $node) {
            if (!isset($visited[$node])) {
                $visit($node);
            }
        }

        return array_reverse($sorted);
    }

    /**
     * كشف الجداول التي تعتبر pivot (جداول ربط)
     */
    protected function detectPivotTables(array $tables): array
    {
        return array_filter($tables, fn($table) =>
            empty($this->getTablePrimaryKeys($table)) ||
            (Str::contains($table, '_') && $this->hasOnlyForeignKeys($table)));
    }

    /**
     * التحقق إذا كان الجدول يحتوي فقط على مفاتيح خارجية (foreign keys) بدون عمود id رئيسي
     */
    protected function hasOnlyForeignKeys(string $table): bool
    {
        $columns = DB::select("SHOW COLUMNS FROM {$table}");
        $primaryKeys = $this->getTablePrimaryKeys($table);
        foreach ($columns as $col) {
            if ($col->Field === 'id')
                return false;
            // يمكن التحقق من نوع العمود أو المفتاح
        }
        return count($primaryKeys) === 0;
    }
}

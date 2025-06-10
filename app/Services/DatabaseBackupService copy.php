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
            $allTablesInDb = $this->getAllTableNames($databaseName);  // جميع الجداول الموجودة فعلياً في قاعدة البيانات

            $excludeTables = array_merge($excludeTables, ['migrations']);
            $this->pivotTables = $this->detectPivotTables($allTablesInDb);

            // الحصول على قائمة بالجداول مرتبة حسب المايجريشن، مع تجميع جداول المايجريشن الواحد
            $migrationGroupedTables = $this->getMigrationGroupedTablesOrder($allTablesInDb);
            $seederClassesToGenerate = [];
            $nextGeneralIndex = 1;
            $processedTables = [];  // لتتبع الجداول التي تم التعامل معها

            foreach ($migrationGroupedTables as $group) {
                foreach ($group as $tableName) {
                    $lowerTableName = strtolower($tableName);

                    // تخطي الجداول المستثناة أو التي تم التعامل معها سابقًا
                    if (in_array($lowerTableName, $excludeTables) || in_array($lowerTableName, $processedTables)) {
                        if (!in_array($lowerTableName, $processedTables)) {  // فقط إذا لم يتم معالجته بعد
                            $report['steps'][] = "⏭️ تم استثناء الجدول: {$tableName}";
                        }
                        continue;
                    }

                    // معالجة الجدول وتوليد الـ Seeder
                    $this->processAndGenerateSeeder($tableName, $nextGeneralIndex, $report, $seederClassesToGenerate);
                    $processedTables[] = $lowerTableName;  // إضافة الجدول إلى قائمة الجداول المعالجة
                }
            }

            // معالجة أي جداول متبقية لم يتم تغطيتها بواسطة المايجريشن
            $remainingTables = array_diff($allTablesInDb, $processedTables, $excludeTables);
            foreach ($remainingTables as $tableName) {
                $this->processAndGenerateSeeder($tableName, $nextGeneralIndex, $report, $seederClassesToGenerate);
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
     * تم مراجعة المسافات هنا بعناية لضمان عدم وجود مسافات غير مرئية (non-breaking spaces).
     */
    protected function generateSeederContent(string $className, string $tableName, array $primaryKeys): string
    {
        $condition = empty($primaryKeys)
            ? 'null'
            : "[\n" . implode('', array_map(fn($key) => "                    '$key' => \$row['$key'],\n", $primaryKeys)) . '                ]';

        $code = empty($primaryKeys)
            ? "DB::table('$tableName')->insert(\$row);"
            : "DB::table('$tableName')->updateOrInsert($condition, \$row);";

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
        // استدعاء ملفات الـ Seeder الفعلية التي تم توليدها
        $seederFiles = File::files($this->seedersBackupPath);
        $seederClassNames = [];

        foreach ($seederFiles as $file) {
            $fileName = $file->getFilenameWithoutExtension();
            if (preg_match('/^N\d{3}_(.+)BackupSeeder$/', $fileName)) {
                $seederClassNames[] = $fileName;
            }
        }

        sort($seederClassNames);  // الترتيب حسب البادئة الرقمية (N001, N002, ...)

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
     * يجلب الجداول وترتيبها من ملفات الهجرة (Migrations)، مع تجميع الجداول التي تم إنشاؤها في نفس المايجريشن.
     * هذا هو الجزء الذي تم تعديله لمعالجة الجداول من الكونفيج.
     */
    protected function getMigrationGroupedTablesOrder(array $allTablesInDb): array
    {
        $files = File::files(database_path('migrations'));

        // فرز ملفات المايجريشن حسب اسم الملف لضمان الترتيب الزمني
        usort($files, function ($a, $b) {
            return strcmp($a->getFilename(), $b->getFilename());
        });

        $orderedGroups = [];
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $fileContent = File::get($filePath);
            $tablesInMigration = [];

            // 1. محاولة استخلاص أسماء الجداول الثابتة (hardcoded strings)
            preg_match_all('/(?:Schema::create|\$this->schema->create)\([\'"]([^\'"]+)[\'"],/', $fileContent, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $tableName) {
                    $lowerTableName = strtolower($tableName);
                    if (in_array($lowerTableName, $allTablesInDb) && !in_array($lowerTableName, $tablesInMigration)) {
                        $tablesInMigration[] = $lowerTableName;
                    }
                }
            }

            // 2. محاولة استخلاص أسماء الجداول من متغيرات الـ config
            // هذا الجزء سيحاول تحليل محتوى المايجريشن للبحث عن استخدام config('permission.table_names')
            // ثم محاولة استنتاج أسماء الجداول الفعلية بناءً على ذلك.
            // هذا النهج أكثر تعقيداً وقد لا يكون فعالاً 100% لكل الحالات
            // لكنه سيغطي حالة Spatie Permissions.

            if (str_contains($fileContent, "config('permission.table_names')")) {
                // محاولة استخلاص أسماء الجداول من ملف الكونفيج مباشرة.
                // يجب أن تكون حذراً جداً عند تضمين ملفات PHP بهذه الطريقة.
                // يفضل أن يكون هذا الكود في بيئة آمنة (مثل بيئة التطوير/الاختبار).

                // حفظ مسار الكونفيج الأصلي
                $permissionConfigPath = config_path('permission.php');

                if (File::exists($permissionConfigPath)) {
                    // تحميل الكونفيج بشكل آمن لقرائته
                    $permissionConfig = require $permissionConfigPath;

                    // استخلاص أسماء الجداول من الكونفيج
                    if (isset($permissionConfig['table_names']) && is_array($permissionConfig['table_names'])) {
                        $configTableNames = [
                            'permissions',
                            'roles',
                            'model_has_permissions',
                            'model_has_roles',
                            'role_has_permissions'
                        ];

                        foreach ($configTableNames as $key) {
                            if (isset($permissionConfig['table_names'][$key])) {
                                $tableNameFromConfig = strtolower($permissionConfig['table_names'][$key]);
                                if (in_array($tableNameFromConfig, $allTablesInDb) && !in_array($tableNameFromConfig, $tablesInMigration)) {
                                    $tablesInMigration[] = $tableNameFromConfig;
                                }
                            }
                        }
                    }
                }
            }

            // إضافة الجداول التي تم العثور عليها كمجموعة
            if (!empty($tablesInMigration)) {
                // التأكد من الترتيب داخل المجموعة نفسها إذا كانت من نفس المايجريشن
                // هذا الجزء يعتمد على الترتيب الذي تظهر به الـ `Schema::create`
                // أو الترتيب المحدد يدوياً للجداول من الكونفيج
                $orderedGroups[] = array_unique($tablesInMigration);  // استخدام array_unique لمنع التكرار
            }
        }
        return $orderedGroups;
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

    /**
     * دالة مساعدة لمعالجة الجدول وتوليد الـ Seeder
     */
    protected function processAndGenerateSeeder(string $tableName, int &$nextGeneralIndex, array &$report, array &$seederClassesToGenerate): void
    {
        try {
            $report['steps'][] = "📦 جاري تصدير الجدول: {$tableName}";
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
                    throw new Exception("لم يتم إنشاء ملف JSON للجدول: {$tableName}");
                }
            }

            $numericPrefix = sprintf('%03d', $nextGeneralIndex++);
            $fullPrefix = 'N' . $numericPrefix;
            $seederClassName = $fullPrefix . '_' . Str::studly($tableName) . 'BackupSeeder';
            $seederFile = "{$this->seedersBackupPath}/{$seederClassName}.php";

            $primaryKeys = $this->getTablePrimaryKeys($tableName);

            $seederContent = $this->generateSeederContent($seederClassName, $tableName, $primaryKeys);
            File::put($seederFile, $seederContent);

            if (!File::exists($seederFile) || filesize($seederFile) === 0) {
                throw new Exception("فشل في توليد Seeder لجدول: {$tableName}");
            }

            $report['steps'][] = "✅ تم توليد Seeder لجدول: {$tableName}";
            $seederClassesToGenerate[] = $seederClassName;
        } catch (Exception $e) {
            $report['errors'][] = "🛑 خطأ أثناء تصدير الجدول '{$tableName}': " . $e->getMessage();
        }
    }
}

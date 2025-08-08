<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAllModelPolicies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-all-model-policies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates a policy for each model in the app/Models folder if it does not already exist.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $modelPath = app_path('Models');
        $modelNamespace = 'App\\Models\\';
        $policyPath = app_path('Policies');

        // استرجاع كل الملفات داخل مجلد Models
        $modelFiles = glob($modelPath . '/*.php');

        foreach ($modelFiles as $modelFile) {
            $modelName = pathinfo($modelFile, PATHINFO_FILENAME);
            $modelClass = $modelNamespace . $modelName;

            // تخطي النماذج التي ليست كلاسات فعلية (مثل Abstract Classes أو Interfaces)
            if (!class_exists($modelClass) || (new \ReflectionClass($modelClass))->isAbstract() || (new \ReflectionClass($modelClass))->isInterface()) {
                $this->warn("تخطي: $modelClass ليس كلاس نموذج فعلي أو قابل للتطبيق.");
                continue;
            }

            // تخطي نموذج User إذا كنت لا تريد إنشاء Policy خاص به بنفس هذا القالب
            // أو إذا كان لديك بالفعل UserPolicy مخصص
            if ($modelName === 'User') {
                $this->info("تخطي: User Policy (افترض أنه يتم التعامل معه بشكل خاص).");
                continue;
            }

            $policyName = $modelName . 'Policy';
            $policyFile = $policyPath . '/' . $policyName . '.php';

            if (file_exists($policyFile)) {
                $this->info("✅ يوجد Policy: $policyName");
                continue;
            }

            // إنشاء ملف Policy بالمحتوى القياسي
            $policyContent = $this->generatePolicyContent($modelName);
            file_put_contents($policyFile, $policyContent);
            $this->info("🛡️ تم إنشاء Policy: $policyName");
        }

        $this->info("🎉 تم التحقق من كل الموديلات وإنشاء ما يلزم من السياسات.");
        $this->warn("ملاحظة هامة: يجب عليك تسجيل هذه الـ Policies يدوياً في App\\Providers\\AuthServiceProvider.php");
        $this->warn("ملاحظة هامة: يجب عليك مراجعة وتعديل مفاتيح الصلاحيات في كل Policy لتناسب النموذج المحدد (مثال: 'products.view_all' بدلاً من 'model_name.view_all').");
    }

    /**
     * يُولّد المحتوى القياسي لملف الـ Policy
     */
    private function generatePolicyContent(string $modelName): string
    {
        $modelVariable = Str::camel($modelName); // e.g., 'account'
        $permissionPrefix = Str::snake($modelName); // e.g., 'account'

        $viewAllPerm = $permissionPrefix . 's.view_all';
        $viewChildrenPerm = $permissionPrefix . 's.view_children';
        $viewSelfPerm = $permissionPrefix . 's.view_self';
        $createPerm = $permissionPrefix . 's.create';
        $updateAllPerm = $permissionPrefix . 's.update_all';
        $updateChildrenPerm = $permissionPrefix . 's.update_children';
        $updateSelfPerm = $permissionPrefix . 's.update_self';
        $deleteAllPerm = $permissionPrefix . 's.delete_all';
        $deleteChildrenPerm = $permissionPrefix . 's.delete_children';
        $deleteSelfPerm = $permissionPrefix . 's.delete_self';
        $restoreAllPerm = $permissionPrefix . 's.restore_all';
        $restoreChildrenPerm = $permissionPrefix . 's.restore_children';
        $restoreSelfPerm = $permissionPrefix . 's.restore_self';
        $forceDeleteAllPerm = $permissionPrefix . 's.force_delete_all';
        $forceDeleteChildrenPerm = $permissionPrefix . 's.force_delete_children';
        $forceDeleteSelfPerm = $permissionPrefix . 's.force_delete_self';


        // التغيير الأساسي هنا:
        // بدلاً من استخدام \$$modelVariable مباشرةً، نستخدم "{\$".$modelVariable."}"
        // وهذا يضمن أن يتم طباعة اسم المتغير مثل '$account' وليس تفسيره كمتغير في وقت توليد الكود.
        return <<<PHP
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\\$modelName;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Traits\Scopes;

class {$modelName}Policy
{
    use HandlesAuthorization, Scopes;

    /**
     * السماح للمسؤول العام بتجاوز جميع السياسات.
     *
     * @param  \App\Models\User  \$user
     * @param  string  \$ability
     * @return \Illuminate\Auth\Access\Response|bool|null
     */
    public function before(User \$user, string \$ability): ?bool
    {
        if (\$user->hasPermissionTo('admin.super')) {
            return true;
        }
        return null;
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض أي $modelName.
     *
     * @param  \App\Models\User  \$user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User \$user): bool
    {
        return \$user->hasAnyPermission([
            '$viewAllPerm',
            '$viewChildrenPerm',
            '$viewSelfPerm',
            'admin.company',
        ], \$user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه عرض الـ $modelName المحدد.
     *
     * @param  \App\Models\User  \$user
     * @param  \\App\\Models\\$modelName  \$$modelVariable // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User \$user, $modelName \${$modelVariable}): bool
    {
        if (!\${$modelVariable}->belongsToCurrentCompany()) {
            return false;
        }

        return \$user->hasPermissionTo('$viewAllPerm', \$user->company_id) ||
               (\$user->hasPermissionTo('$viewChildrenPerm', \$user->company_id) && \${$modelVariable}->createdByUserOrChildren()) ||
               (\$user->hasPermissionTo('$viewSelfPerm', \$user->company_id) && \${$modelVariable}->createdByCurrentUser()) ||
               \$user->hasPermissionTo('admin.company', \$user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه إنشاء $modelName.
     *
     * @param  \App\Models\User  \$user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User \$user): bool
    {
        return \$user->hasAnyPermission([
            '$createPerm',
            'admin.company',
        ], \$user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه تحديث الـ $modelName المحدد.
     *
     * @param  \App\Models\User  \$user
     * @param  \\App\\Models\\$modelName  \$$modelVariable // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User \$user, $modelName \${$modelVariable}): bool
    {
        if (!\${$modelVariable}->belongsToCurrentCompany()) {
            return false;
        }

        return \$user->hasPermissionTo('$updateAllPerm', \$user->company_id) ||
               (\$user->hasPermissionTo('$updateChildrenPerm', \$user->company_id) && \${$modelVariable}->createdByUserOrChildren()) ||
               (\$user->hasPermissionTo('$updateSelfPerm', \$user->company_id) && \${$modelVariable}->createdByCurrentUser()) ||
               \$user->hasPermissionTo('admin.company', \$user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ $modelName المحدد.
     *
     * @param  \App\Models\User  \$user
     * @param  \\App\\Models\\$modelName  \$$modelVariable // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User \$user, $modelName \${$modelVariable}): bool
    {
        if (!\${$modelVariable}->belongsToCurrentCompany()) {
            return false;
        }

        return \$user->hasPermissionTo('$deleteAllPerm', \$user->company_id) ||
               (\$user->hasPermissionTo('$deleteChildrenPerm', \$user->company_id) && \${$modelVariable}->createdByUserOrChildren()) ||
               (\$user->hasPermissionTo('$deleteSelfPerm', \$user->company_id) && \${$modelVariable}->createdByCurrentUser()) ||
               \$user->hasPermissionTo('admin.company', \$user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه استعادة الـ $modelName المحدد.
     *
     * @param  \App\Models\User  \$user
     * @param  \\App\\Models\\$modelName  \$$modelVariable // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User \$user, $modelName \${$modelVariable}): bool
    {
        if (!\${$modelVariable}->belongsToCurrentCompany()) {
            return false;
        }
        return \$user->hasPermissionTo('$restoreAllPerm', \$user->company_id) ||
               (\$user->hasPermissionTo('$restoreChildrenPerm', \$user->company_id) && \${$modelVariable}->createdByUserOrChildren()) ||
               (\$user->hasPermissionTo('$restoreSelfPerm', \$user->company_id) && \${$modelVariable}->createdByCurrentUser()) ||
               \$user->hasPermissionTo('admin.company', \$user->company_id);
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه حذف الـ $modelName المحدد بشكل دائم.
     *
     * @param  \App\Models\User  \$user
     * @param  \\App\\Models\\$modelName  \$$modelVariable // تصحيح هنا
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User \$user, $modelName \${$modelVariable}): bool
    {
        if (!\${$modelVariable}->belongsToCurrentCompany()) {
            return false;
        }
        return \$user->hasPermissionTo('$forceDeleteAllPerm', \$user->company_id) ||
               (\$user->hasPermissionTo('$forceDeleteChildrenPerm', \$user->company_id) && \${$modelVariable}->createdByUserOrChildren()) ||
               (\$user->hasPermissionTo('$forceDeleteSelfPerm', \$user->company_id) && \${$modelVariable}->createdByCurrentUser()) ||
               \$user->hasPermissionTo('admin.company', \$user->company_id);
    }
}

PHP;
    }
}

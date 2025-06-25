<?php

namespace App\Models;

use App\Traits\LogsActivity;
use App\Traits\RolePermissions;  // افترض أن هذا trait مخصص ومطلوب
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;  // إضافة هذه الواجهة للعلاقة ManyToMany
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @mixin IdeHelperRole
 */
class Role extends SpatieRole implements RoleContract
{
    // HasRoles و HasPermissions متوفرتان بالفعل من SpatieRole، لذا لا حاجة لتكرارهما هنا.
    // افترض أن Scopes و LogsActivity و RolePermissions traits مخصصة ومطلوبة.
    use Scopes, LogsActivity, RolePermissions;

    protected $fillable = [
        'name',
        'guard_name',
        'created_by',
        // 'company_id' يجب ألا تكون هنا، لأن الدور نفسه ليس مرتبطًا بشركة واحدة مباشرة.
        // الارتباط بالشركات يتم عبر الجدول الوسيط 'role_company'.
    ];

    /**
     * العلاقة التي تحدد المستخدم الذي أنشأ هذا الدور.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function companies(): BelongsToMany
    {
        // يربط نموذج Role بالشركات عبر جدول 'role_company'
        // 'role_id' هو المفتاح الخارجي للدور في الجدول الوسيط
        // 'company_id' هو المفتاح الخارجي للشركة في الجدول الوسيط
        return $this
            ->belongsToMany(Company::class, 'role_company', 'role_id', 'company_id')
            ->using(RoleCompany::class)  // **** التعديل الرئيسي هنا: تحديد نموذج Pivot المخصص ****
            ->withPivot('created_by')  // لإضافة عمود created_by من الجدول الوسيط
            ->withTimestamps();  // لإضافة created_at و updated_at للجدول الوسيط
    }

    // يمكن إضافة دوال أو منطق إضافي هنا إذا لزم الأمر
}

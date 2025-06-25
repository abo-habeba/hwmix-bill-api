<?php

namespace App\Models;

use App\Traits\Scopes;
// يجب أن يمتد نموذج Pivot من Illuminate\Database\Eloquent\Relations\Pivot
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;  // استيراد فئة Pivot
// Spatie\Permission\Models\Permission لا داعي لاستيرادها هنا إذا لم يتم استخدامها مباشرة.

/**
 * @mixin IdeHelperRoleCompany
 */
class RoleCompany extends Pivot  // **** التعديل الرئيسي: يجب أن يمتد من Pivot ****
{
    use HasFactory, Scopes;

    // اسم الجدول الذي يمثله هذا النموذج
    protected $table = 'role_company';

    // الأعمدة القابلة للتعبئة الجماعية
    protected $fillable = ['role_id', 'company_id', 'created_by'];

    // إذا لم تكن تستخدم معرّفًا أساسيًا (ID) في جدول pivot،
    // يمكنك تعيين public $incrementing = false;
    // ومع ذلك، بما أن جدولك يحتوي على $table->id()، فلا حاجة لهذا.

    /**
     * العلاقة التي تربط هذا السجل الوسيط بالدور المحدد.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * العلاقة التي تربط هذا السجل الوسيط بالشركة المحددة.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * العلاقة التي تحدد المستخدم الذي أنشأ هذا السجل الوسيط.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\HasImages;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Translations\Translatable;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyUser extends Model
{
    use HasFactory, Translatable, HasRoles, Filterable, Scopes, HasPermissions, LogsActivity, HasImages;

    /**
     * اسم الجدول المرتبط بالموديل.
     *
     * @var string
     */

    /**
     * الحقول التي يمكن تعبئتها جماعياً.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * العلاقات التي يجب تحميلها تلقائيا عند جلب الموديل.
     *
     * @var array
     */
    protected $with = [
        'user',
        'company'
    ];


    /**
     * الحصول على المستخدم المرتبط بسجل الشركة.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * الحصول على الشركة المرتبطة بسجل المستخدم.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * الحصول على من قام بإنشاء هذا السجل في company_user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyUser extends Model
{
    use HasFactory;

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

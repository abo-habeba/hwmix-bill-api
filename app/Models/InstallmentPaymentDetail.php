<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // إضافة استيراد BelongsTo
use App\Traits\Blameable; // إذا كان هذا النموذج يستخدم Blameable
use App\Traits\Scopes; // إذا كان هذا النموذج يستخدم Scopes

class InstallmentPaymentDetail extends Model
{
    // أضف Traits إذا كانت مستخدمة في هذا النموذج
    use Blameable, Scopes;

    protected $table = 'installment_payment_details';
    protected $guarded = []; // أو يمكنك استخدام $fillable لتحديد الحقول المسموح بها

    /**
     * العلاقة مع الدفعة الرئيسية (Payment) التي تغطي هذا القسط.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * العلاقة مع القسط الفردي الذي تم دفعه.
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    // إذا كان النموذج يستخدم created_by و company_id، فقد تحتاج إلى تعريف العلاقات التالية
    // public function company(): BelongsTo
    // {
    //     return $this->belongsTo(Company::class);
    // }

    // public function creator(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'created_by');
    // }
}

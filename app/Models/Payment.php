<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperPayment
 */
class Payment extends Model
{
    use HasFactory, Blameable, Scopes;

    protected $fillable = [
        'user_id',
        'company_id', // تأكد أن هذا موجود في الـ fillable إذا لم يكن
        'created_by', // تأكد أن هذا موجود في الـ fillable إذا لم يكن
        'payment_date',
        'amount',
        'method',
        'notes',
        'is_split',
        'payment_type', // حقل جديد
        'cash_box_id', // حقل جديد
        'financial_transaction_id', // حقل جديد
        'payable_type', // حقل جديد
        'payable_id', // حقل جديد
    ];

    /**
     * العلاقة مع المستخدم الذي قام بالدفع/الاستلام.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة مع الصندوق النقدي الذي تأثر بالدفعة.
     */
    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    /**
     * العلاقة مع المعاملة المالية المرتبطة بهذه الدفعة.
     */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /**
     * العلاقة Polymorphic مع الكيان الذي تم الدفع لأجله (مثل فاتورة، قسط).
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * العلاقة مع تفاصيل دفعات الأقساط المرتبطة بهذه الدفعة.
     * (تحل محل علاقة installments القديمة)
     */
    public function installmentPaymentDetails(): HasMany
    {
        return $this->hasMany(InstallmentPaymentDetail::class, 'payment_id');
    }

    // تم حذف علاقة paymentMethod() لأن 'method' أصبح حقلاً نصياً
    // تم حذف علاقة installments() لأن جدول payment_installment تم حذفه
}

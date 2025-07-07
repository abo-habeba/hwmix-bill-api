<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperInstallmentPlan
 */
class InstallmentPlan extends Model
{
    use HasFactory, Blameable, Scopes;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'total_amount',
        'down_payment',
        'remaining_amount',
        'company_id',
        'created_by',
        'number_of_installments',
        'installment_amount',
        'start_date',
        'end_date',
        'status',
        'notes'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    // الفاتورة المرتبطة بخطة التقسيط
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // العميل المرتبط بالخطة
    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // الأقساط التابعة للخطة
    public function installments()
    {
        return $this->hasMany(Installment::class);
    }

    // المستخدم المرتبط بالخطة (نفس العميل)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // المستخدم اللي أنشأ الخطة
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // الشركة المرتبطة بالخطة
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // المدفوعات المرتبطة بالخطة (لو ليها جدول معين)
    public function payments()
    {
        return $this->hasMany(InstallmentPayment::class);
    }
}

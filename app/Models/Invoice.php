<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use App\Models\InvoiceType;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory, LogsActivity, Blameable, Scopes, SoftDeletes;

    // بما أن $guarded = []، فهذا يعني أن جميع الحقول قابلة للملء جماعياً.
    // لكن من الجيد التأكد من أن الحقول الجديدة موجودة في الهجرة.
    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $type = InvoiceType::find($invoice->invoice_type_id);
                $companyId = Auth::user()->company_id; // استخدام Auth::user()
                $invoice->invoice_number = self::generateInvoiceNumber($type->code, $companyId);
            }

            $invoice->company_id = $invoice->company_id ?? Auth::user()->company_id;
            $invoice->created_by = $invoice->created_by ?? Auth::id();

            // حساب الربح التقديري عند إنشاء الفاتورة
            // هذا المنطق يجب أن يكون في Service Layer أو في حدث منفصل
            // لكن كمثال مبدئي يمكن وضعه هنا
            // $invoice->estimated_profit = $invoice->items->sum(function ($item) {
            //     return ($item->quantity * $item->unit_price) - ($item->quantity * $item->cost_price);
            // });
        });

        static::updating(function ($invoice) {
            $invoice->updated_by = Auth::id(); // استخدام Auth::id()
            // تحديث الربح التقديري عند تحديث الفاتورة (إذا تغيرت البنود)
            // هذا المنطق يجب أن يكون في Service Layer أو في حدث منفصل
            // $invoice->estimated_profit = $invoice->items->sum(function ($item) {
            //     return ($item->quantity * $item->unit_price) - ($item->quantity * $item->cost_price);
            // });
        });
    }

    public static function generateInvoiceNumber($typeCode, $companyId): string
    {
        $datePart = now()->format('ymd');
        $lastInvoice = self::where('company_id', $companyId)
            ->whereHas('invoiceType', fn($query) => $query->where('code', $typeCode))
            ->latest('id')
            ->first();
        $lastSerial = $lastInvoice ? (int) substr($lastInvoice->invoice_number, -6) : 0;
        $nextSerial = str_pad($lastSerial + 1, 6, '0', STR_PAD_LEFT);
        return strtoupper(self::shortenTypeCode($typeCode)) . '-' . $datePart . '-' . $companyId . '-' . $nextSerial;
    }

    public static function shortenTypeCode(string $typeCode): string
    {
        $parts = explode('_', $typeCode);
        return count($parts) === 1
            ? substr($typeCode, 0, 4)
            : implode('_', array_map(fn($p) => substr($p, 0, 3), $parts));
    }

    /**
     * العلاقة مع المستخدم (العميل) المرتبط بالفاتورة.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة مع نوع الفاتورة.
     */
    public function invoiceType(): BelongsTo
    {
        return $this->belongsTo(InvoiceType::class);
    }

    /**
     * العلاقة مع بنود الفاتورة.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * العلاقة مع بنود الفاتورة، بما في ذلك المحذوفة ناعمًا.
     */
    public function itemsWithTrashed(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->withTrashed();
    }

    /**
     * العلاقة مع الشركة التي تنتمي إليها الفاتورة.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * العلاقة مع المستخدم الذي أنشأ الفاتورة.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * العلاقة مع المستخدم الذي قام بآخر تحديث للفاتورة.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * العلاقة مع خطة التقسيط المرتبطة بالفاتورة.
     */
    public function installmentPlan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class, 'invoice_id');
    }

    /**
     * العلاقة مع الصندوق النقدي الافتراضي للعميل المرتبط بالفاتورة.
     * (إذا كنت لا تزال تستخدم هذا الربط المباشر من الفاتورة)
     */
    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'cash_box_id');
    }

    /**
     * العلاقة مع المدفوعات المرتبطة بهذه الفاتورة (علاقة Polymorphic).
     * (إذا كنت ستستخدم هذه العلاقة للوصول إلى المدفوعات التي تشير إلى هذه الفاتورة)
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payable_id')->where('payable_type', self::class);
    }
}

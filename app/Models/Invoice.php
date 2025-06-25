<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\LogsActivity;
use App\Traits\Blameable;

/**
 * @mixin IdeHelperInvoice
 */
class Invoice extends Model
{
    use HasFactory, LogsActivity, Blameable;

    protected $fillable = [
        'company_id',
        'user_id',
        'created_by',
        'invoice_number',
        'invoice_type_id',
        'due_date',
        'status',
        'total_amount',
        'notes',
        'installment_plan_id',
    ];

    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $type = InvoiceType::find($invoice->invoice_type_id);
                $companyId = Auth::user()->company_id;
                $invoice->invoice_number = self::generateInvoiceNumber($type->code, $companyId);
            }

            // Set default values for company_id and created_by
            $invoice->company_id = $invoice->company_id ?? Auth::user()->company_id;
            $invoice->created_by = $invoice->created_by ?? Auth::id();
        });
    }

    public static function generateInvoiceNumber($typeCode, $companyId)
    {
        $datePart = now()->format('ymd');
        $lastInvoice = self::where('company_id', $companyId)
            ->whereHas('invoiceType', function ($query) use ($typeCode) {
                $query->where('code', $typeCode);
            })
            ->latest('id')
            ->first();
        $lastSerial = 0;
        if ($lastInvoice) {
            $lastSerial = (int) substr($lastInvoice->invoice_number, -6);
        }
        $nextSerial = str_pad($lastSerial + 1, 6, '0', STR_PAD_LEFT);
        return strtoupper(self::shortenTypeCode($typeCode)) . '-' . $datePart . '-' . $companyId . '-' . $nextSerial;
    }

    public static function shortenTypeCode(string $typeCode): string
    {
        $parts = explode('_', $typeCode);
        if (count($parts) === 1) {
            // كلمة واحدة: خذ أول 4 أحرف
            return substr($typeCode, 0, 4);
        }
        // كلمتين أو أكثر: خذ أول حرفين من كل كلمة (حد أقصى 2 كلمات)
        $shortenedParts = [];
        foreach ($parts as $part) {
            $shortenedParts[] = substr($part, 0, 3);
        }

        return implode('_', $shortenedParts);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoiceType()
    {
        return $this->belongsTo(InvoiceType::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function installmentPlan()
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }
}

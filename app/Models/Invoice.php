<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Invoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id',
        'user_id',
        'created_by',
        'invoice_type_id',
        'invoice_number',
        'issue_date',
        'due_date',
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
        return strtoupper($typeCode) . $datePart . $companyId . $nextSerial;
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

<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Invoice extends Model
{
    use HasFactory, LogsActivity, Blameable, Scopes, SoftDeletes;

    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $type = InvoiceType::find($invoice->invoice_type_id);
                $companyId = Auth::user()->company_id;
                $invoice->invoice_number = self::generateInvoiceNumber($type->code, $companyId);
            }

            $invoice->company_id = $invoice->company_id ?? Auth::user()->company_id;
            $invoice->created_by = $invoice->created_by ?? Auth::id();
        });

        static::updating(function ($invoice) {
            $invoice->updated_by = Auth::id();
        });
    }

    public static function generateInvoiceNumber($typeCode, $companyId)
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
    public function itemsWithTrashed()
    {
        return $this->hasMany(InvoiceItem::class)->withTrashed();
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function installmentPlan()
    {
        return $this->hasOne(InstallmentPlan::class, 'invoice_id');
    }
}

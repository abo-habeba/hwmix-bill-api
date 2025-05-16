<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'invoice_type_id', 'invoice_number', 'issue_date', 'due_date',
        'total_amount', 'status', 'notes'
    ];
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
    public function installmentPlan()
    {
        return $this->hasOne(InstallmentPlan::class);
    }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\Blameable;

class InvoiceItem extends Model
{
    use HasFactory, Blameable;

    protected $fillable = [
        'invoice_id', 'product_id', 'installment_number', 'name', 'quantity', 'unit_price', 'discount', 'total', 'company_id', 'created_by'
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

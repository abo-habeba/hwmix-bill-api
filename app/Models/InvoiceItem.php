<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Blameable;
use App\Traits\Scopes;

/**
 * @mixin IdeHelperInvoiceItem
 */
class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes, Blameable, Scopes;

    protected $guarded = [];
    // 🔗 العلاقة مع الفاتورة
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    // 🔗 العلاقة مع المنتج الأساسي
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    // 🔗 العلاقة مع متغير المنتج
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
    // 🔗 العلاقة مع الشركة
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    // 🔗 علاقة المنشئ
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    // 🔗 علاقة المعدّل
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

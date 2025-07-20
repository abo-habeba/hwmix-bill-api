<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperProductVariantAttribute
 */
class ProductVariantAttribute extends Model
{
    use HasFactory, Blameable, Scopes;

    protected $fillable = [
        'product_variant_id',
        'attribute_id',
        'attribute_value_id',
        'company_id',
        'created_by'
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    public function attributeValue()
    {
        return $this->belongsTo(AttributeValue::class, 'attribute_value_id');
    }


    // هذا ليس له علاقة واضحة، وحيربكك
    // يمكن حذفه لأنه يربط بقيم متعددة بينما الحقل attribute_value_id يربط بواحد فقط
    // public function values()
    // {
    //     return $this->hasMany(AttributeValue::class);
    // }
}

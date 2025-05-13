<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariantAttribute extends Model
{
    use HasFactory;

    protected $fillable = ['product_variant_id', 'attribute_id', 'attribute_value_id'];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    public function attributeValue()
    {
        return $this->belongsTo(AttributeValue::class);
    }
}

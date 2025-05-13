<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariantAttribute extends Model
{
    protected $fillable = ['variant_id', 'attribute_id', 'attribute_value_id'];

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    public function attributeValue()
    {
        return $this->belongsTo(AttributeValue::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

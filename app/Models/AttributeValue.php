<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'attribute_id',
        'created_by',
        'name',
        'color'
        ];

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
}

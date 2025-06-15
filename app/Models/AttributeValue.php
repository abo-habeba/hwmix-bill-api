<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Blameable;

class AttributeValue extends Model
{
    use HasFactory, Blameable;

    protected $fillable = [
        'attribute_id',
        'created_by',
        'name',
        'color',
        'company_id'
    ];

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
}

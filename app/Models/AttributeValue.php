<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperAttributeValue
 */
class AttributeValue extends Model
{
    use HasFactory, Blameable, Scopes;

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

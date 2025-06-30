<?php

namespace App\Models;

use App\Models\VariantAttribute;
use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperVariant
 */
class Variant extends Model
{
    use HasFactory, Scopes, Blameable;

    protected $fillable = ['product_id', 'name', 'price'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes()
    {
        return $this->hasMany(VariantAttribute::class);
    }
}

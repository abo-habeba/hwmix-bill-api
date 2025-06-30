<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Blameable;
use App\Traits\Scopes;
/**
 * @mixin IdeHelperBrand
 */
class Brand extends Model
{
    use HasFactory, Blameable,Scopes;

    protected $fillable = ['company_id', 'created_by', 'name', 'description'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Blameable;

/**
 * @mixin IdeHelperAttribute
 */
class Attribute extends Model
{
    use HasFactory, Blameable;

    protected $fillable = ['name', 'value', 'company_id', 'created_by'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

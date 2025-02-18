<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'value', 'company_id', 'created_by'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function values() {
        return $this->hasMany(AttributeValue::class);
    }
}

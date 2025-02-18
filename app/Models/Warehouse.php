<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'created_by', 'name', 'location'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}


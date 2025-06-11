<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory, \App\Traits\Blameable;

    protected $fillable = [
        'name', 'location', 'manager', 'capacity', 'status', 'company_id', 'created_by'
    ];

    protected $casts = [
        'capacity' => 'integer',
        'status' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}

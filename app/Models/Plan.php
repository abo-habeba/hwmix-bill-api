<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';
    protected $guarded = [];

    // علاقات شائعة
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}

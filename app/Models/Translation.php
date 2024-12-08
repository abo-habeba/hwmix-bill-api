<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $fillable = ['locale', 'field', 'value'];

    // علاقة Polymorphic
    public function model()
    {
        return $this->morphTo();
    }
}

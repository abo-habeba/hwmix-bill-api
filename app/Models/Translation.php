<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Translation extends Model
{
    protected $fillable = ['locale', 'field', 'value'];

    // علاقة Polymorphic
    public function model()
    {
        return $this->morphTo();
    }
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

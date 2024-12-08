<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $fillable = ['action', 'model', 'model_id', 'changes', 'user_id'];
}

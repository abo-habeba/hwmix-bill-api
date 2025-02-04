<?php

namespace App\Models;

use App\Traits\Scopes;
use App\Traits\LogsActivity;
use App\Traits\RolePermissions;
use Illuminate\Database\Eloquent\Model;

class CashBoxType extends Model
{
    use Scopes, LogsActivity, RolePermissions;
    protected $table = 'cash_box_types';

    protected $fillable = [
        'name',
        'description',
    ];

    // العلاقة مع الخزائن (CashBox)
    public function cashBoxes()
    {
        return $this->hasMany(CashBox::class, 'cash_box_type_id');
    }
}

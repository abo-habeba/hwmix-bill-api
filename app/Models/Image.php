<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperImage
 */
class Image extends Model
{
    use Blameable, Scopes;

    protected $fillable = ['url', 'type', 'imageable_id', 'imageable_type', 'company_id', 'created_by'];

    public function imageable()
    {
        return $this->morphTo();
    }
}

<?php

namespace App\Models;

use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @mixin IdeHelperCategory
 */
class Category extends Model
{
    use HasFactory, Scopes;

    protected $fillable = ['company_id', 'created_by', 'parent_id', 'name', 'description'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->with('children');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id'); // تأكد من اسم المفتاح الأجنبي 'creator_id'
    }
}

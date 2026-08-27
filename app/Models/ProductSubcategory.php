<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSubcategory extends Model
{
    use SoftDeletes;

    // FIX: 'status' was in fillable but is NOT a column in the migration.
    // Keeping it causes mass-assignment to silently try setting a non-existent
    // column. Removed. If you need status later, add the migration column first.
    // 'description' added since the controller passes it via $request->only().
    protected $fillable = ['category_id', 'name', 'code', 'description'];

    // ── Relationships ────────────────────────────────────────────────

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'subcategory_id');
    }
}
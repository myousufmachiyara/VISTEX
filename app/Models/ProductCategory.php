<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code'];

    // ── Relationships ────────────────────────────────────────────────

    public function subcategories()
    {
        return $this->hasMany(ProductSubcategory::class, 'category_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function stockAccount() { return $this->belongsTo(ChartOfAccounts::class, 'stock_account_id'); }
    public function cogsAccount()  { return $this->belongsTo(ChartOfAccounts::class, 'cogs_account_id'); }
    public function incharges()     { return $this->hasMany(CategoryIncharge::class, 'product_category_id'); }
    public function inchargeUsers() { return $this->belongsToMany(User::class, 'category_incharges', 'product_category_id', 'user_id'); }
    // FIX: removed productions() — Production model does not exist in this project.
    // It was left over from another project and would throw a class-not-found error
    // the first time any code eager-loads 'productions' on a category.
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryIncharge extends Model
{
    protected $table = 'category_incharges';

    protected $fillable = ['product_category_id', 'user_id'];

    public function category() { return $this->belongsTo(ProductCategory::class, 'product_category_id'); }
    public function user()     { return $this->belongsTo(User::class, 'user_id'); }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionConsumption extends Model
{
    protected $fillable = ['production_order_id', 'production_order_line_id', 'product_id', 'quantity', 'amount'];
    protected $casts = ['quantity' => 'float', 'amount' => 'float'];

    public function product() { return $this->belongsTo(Product::class); }
}

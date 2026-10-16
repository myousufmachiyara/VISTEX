<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PutUpPackaging extends Model
{
    protected $fillable = ['put_up_product_id', 'material_product_id', 'qty_per_piece'];
    protected $casts = ['qty_per_piece' => 'float'];

    public function putUp()    { return $this->belongsTo(Product::class, 'put_up_product_id'); }
    public function material() { return $this->belongsTo(Product::class, 'material_product_id'); }
}

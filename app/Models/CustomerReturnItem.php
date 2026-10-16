<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReturnItem extends Model
{
    protected $fillable = ['customer_return_id', 'stock_movement_item_id', 'product_id', 'lot_no', 'grade', 'quantity', 'condition', 'cost_amount', 'uninvoiced_qty', 'credited_qty'];
    protected $casts = ['quantity' => 'float', 'cost_amount' => 'float', 'uninvoiced_qty' => 'float', 'credited_qty' => 'float'];

    public function product()           { return $this->belongsTo(Product::class); }
    public function stockMovementItem() { return $this->belongsTo(StockMovementItem::class); }
}

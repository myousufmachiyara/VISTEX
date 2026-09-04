<?php
// app/Models/StockMovementItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovementItem extends Model
{
    protected $table = 'stock_movement_items';
    protected $fillable = ['stock_movement_id', 'product_id', 'quantity', 'amount'];
    protected $casts = ['quantity' => 'decimal:3', 'amount' => 'decimal:2'];

    public function stockMovement() { return $this->belongsTo(StockMovement::class, 'stock_movement_id'); }
    public function product()       { return $this->belongsTo(Product::class, 'product_id'); }
}
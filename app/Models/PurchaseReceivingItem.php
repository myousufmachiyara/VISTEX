<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceivingItem extends Model
{
    protected $table = 'purchase_receiving_items';
    protected $fillable = ['purchase_receiving_id', 'purchase_order_item_id', 'product_id', 'quantity_received', 'rate', 'amount'];
    protected $casts = ['quantity_received' => 'decimal:3', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];

    public function purchaseReceiving() { return $this->belongsTo(PurchaseReceiving::class, 'purchase_receiving_id'); }
    public function purchaseOrderItem() { return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id'); }
    public function product()           { return $this->belongsTo(Product::class, 'product_id'); }
}
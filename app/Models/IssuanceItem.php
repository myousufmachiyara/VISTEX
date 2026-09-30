<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssuanceItem extends Model
{
    protected $table = 'issuance_items';
    protected $fillable = ['issuance_id', 'product_id', 'lot_no', 'purchase_order_item_id', 'quantity', 'rate', 'amount'];
    protected $casts = ['quantity' => 'decimal:3', 'rate' => 'decimal:4', 'amount' => 'decimal:2'];

    public function issuance()          { return $this->belongsTo(Issuance::class, 'issuance_id'); }
    public function product()           { return $this->belongsTo(Product::class, 'product_id'); }
    public function purchaseOrderItem() { return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id'); }
}

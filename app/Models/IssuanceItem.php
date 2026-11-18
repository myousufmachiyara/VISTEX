<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssuanceItem extends Model
{
    protected $table = 'issuance_items';
    protected $fillable = ['issuance_id', 'product_id', 'lot_no', 'grade', 'purchase_order_item_id', 'job_item_id', 'quantity', 'bags', 'rate', 'amount'];
    protected $casts = ['quantity' => 'decimal:3', 'rate' => 'decimal:4', 'amount' => 'decimal:2'];

    public function issuance()          { return $this->belongsTo(Issuance::class, 'issuance_id'); }
    public function product()           { return $this->belongsTo(Product::class, 'product_id'); }
    public function purchaseOrderItem() { return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id'); }
    public function jobItem()           { return $this->belongsTo(JobItem::class, 'job_item_id'); }

    // Processed fabric is stocked in meters; cutting & packing work in yards
    public function getQuantityYdAttribute(): float { return Issuance::toYards((float) $this->quantity); }
}

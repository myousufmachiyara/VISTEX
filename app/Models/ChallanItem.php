<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallanItem extends Model
{
    protected $table = 'challan_items';

    protected $fillable = [
        'challan_id', 'purchase_order_item_id', 'product_id', 'description',
        'expected_qty', 'received_qty', 'decision', 'rejection_note',
    ];

    protected $casts = [
        'expected_qty' => 'decimal:3',
        'received_qty' => 'decimal:3',
    ];

    public function challan()
    {
        return $this->belongsTo(Challan::class, 'challan_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
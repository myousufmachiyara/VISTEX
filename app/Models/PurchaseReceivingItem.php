<?php
// app/Models/PurchaseReceivingItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceivingItem extends Model
{
    protected $table = 'purchase_receiving_items';

    protected $fillable = ['purchase_receiving_id', 'purchase_order_item_id', 'product_id', 'quantity_received', 'quantity_rejected', 'quantity_returned', 'rate', 'amount', 'qty_a', 'qty_b', 'qty_c'];

    protected $casts = [
        'quantity_received' => 'decimal:3',
        'quantity_rejected' => 'decimal:3',
        'quantity_returned' => 'decimal:3',
        'rate'              => 'decimal:2',
        'amount'            => 'decimal:2',
    ];

    public function purchaseReceiving() { return $this->belongsTo(PurchaseReceiving::class, 'purchase_receiving_id'); }
    public function purchaseOrderItem() { return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id'); }
    public function product()           { return $this->belongsTo(Product::class, 'product_id'); }

    // "A 120 · B 8 · C 2" for processed-fabric lines
    public function getGradeLabelAttribute(): ?string
    {
        $parts = [];
        foreach (['A' => 'qty_a', 'B' => 'qty_b', 'C' => 'qty_c'] as $g => $c) {
            if ((float) $this->{$c} > 0) $parts[] = $g . ' ' . rtrim(rtrim(number_format((float) $this->{$c}, 3), '0'), '.');
        }
        return $parts ? implode(' · ', $parts) : null;
    }

    public function getQuantityAcceptedAttribute(): float
    {
        return round((float) $this->quantity_received - (float) $this->quantity_rejected, 3);
    }

    public function getQuantityPendingReturnAttribute(): float
    {
        return round((float) $this->quantity_rejected - (float) $this->quantity_returned, 3);
    }
}
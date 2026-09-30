<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';
    protected $fillable = [
        'purchase_order_id', 'product_id', 'forecast_id', 'job_item_id', 'measurement_unit', 'pack_qty', 'qty_per_pack',
        'collection', 'pattern_code', 'description', 'quantity', 'quantity_received', 'rate', 'amount',
    ];
    protected $casts = ['pack_qty' => 'decimal:3', 'qty_per_pack' => 'decimal:4', 'quantity' => 'decimal:3', 'quantity_received' => 'decimal:3', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];

    // "20 × 10" when the line was entered in packs of more than 1 unit
    public function getPackingLabelAttribute(): ?string
    {
        if ($this->pack_qty === null || (float) $this->qty_per_pack == 1.0) return null;
        $fmt = fn($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.');
        return $fmt($this->pack_qty) . ' × ' . $fmt($this->qty_per_pack);
    }

    public function purchaseOrder()   { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function product()         { return $this->belongsTo(Product::class, 'product_id'); }
    public function forecast()        { return $this->belongsTo(Forecast::class, 'forecast_id'); }
    public function jobItem()         { return $this->belongsTo(JobItem::class, 'job_item_id'); }
    public function measurementUnit() { return $this->belongsTo(MeasurementUnit::class, 'measurement_unit'); }

    public function getOutstandingQtyAttribute(): float
    {
        return round((float) $this->quantity - (float) $this->quantity_received, 3);
    }
}
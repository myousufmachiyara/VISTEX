<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';
    protected $fillable = [
        'purchase_order_id', 'product_id', 'forecast_id', 'job_item_id', 'measurement_unit',
        'collection', 'pattern_code', 'description', 'quantity', 'quantity_received', 'rate', 'amount',
    ];
    protected $casts = ['quantity' => 'decimal:3', 'quantity_received' => 'decimal:3', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];

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
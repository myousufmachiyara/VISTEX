<?php
// app/Models/StockMovementItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovementItem extends Model
{
    protected $table = 'stock_movement_items';
    protected $fillable = ['stock_movement_id', 'product_id', 'source_lot_no', 'source_status', 'source_grade', 'sale_order_item_id', 'job_item_id', 'invoiced_qty', 'quantity', 'amount'];
    protected $casts = ['quantity' => 'decimal:3', 'amount' => 'decimal:2'];

    // What the line takes at the source: fresh stock (optionally one grade) or the QC reprocess hold
    public function getSourceLabelAttribute(): string
    {
        if ($this->source_status === 'reprocess') return 'Reprocess hold';
        return $this->source_grade ? "Grade {$this->source_grade}" : 'Fresh';
    }

    public function saleOrderItem() { return $this->belongsTo(SaleOrderItem::class); }
    public function jobItem()       { return $this->belongsTo(JobItem::class, 'job_item_id'); }

    public function stockMovement() { return $this->belongsTo(StockMovement::class, 'stock_movement_id'); }
    public function product()       { return $this->belongsTo(Product::class, 'product_id'); }
}
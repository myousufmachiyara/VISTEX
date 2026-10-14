<?php
// app/Models/JobItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobItem extends Model
{
    protected $table = 'job_order_items';

    protected $fillable = [
        'job_id', 'collection', 'pattern_code', 'design', 'color_scheme',
        'product_id', 'quantity', 'measurement_unit',
        'unit_price', 'discount_pct', 'tax_id', 'tax_amount', 'amount', 'quantity_fulfilled',
    ];

    protected $casts = [
        'quantity'            => 'decimal:3',
        'unit_price'          => 'decimal:4',
        'discount_pct'        => 'decimal:2',
        'tax_amount'          => 'decimal:2',
        'amount'              => 'decimal:2',
        'quantity_fulfilled'  => 'decimal:3',
    ];

    public function job()             { return $this->belongsTo(Job::class, 'job_id'); }
    public function product()         { return $this->belongsTo(Product::class, 'product_id'); }
    public function measurementUnit() { return $this->belongsTo(MeasurementUnit::class, 'measurement_unit'); }
    public function tax()             { return $this->belongsTo(TaxMaster::class, 'tax_id'); }

    // Pieces of the chosen put-up this quantity makes (qty in meters / meters per piece)
    public function getPiecesAttribute(): ?float
    {
        $perPiece = (float) ($this->product?->getAttribute('attributes')['meters_per_unit'] ?? 0);
        return $perPiece > 0 ? floor((float) $this->quantity / $perPiece) : null;
    }

    public function getOutstandingQtyAttribute(): float
    {
        return round((float) $this->quantity - (float) $this->quantity_fulfilled, 3);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'category_id', 'name', 'sku', 'description', 'attributes',
        'opening_stock', 'selling_price', 'measurement_unit',
        'is_active', 'track_lots', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'attributes'      => 'array',
        'opening_stock'   => 'decimal:3',
        'selling_price'   => 'decimal:2',
        'is_active'       => 'boolean',
        'track_lots'      => 'boolean',
    ];

    public function category()        { return $this->belongsTo(ProductCategory::class, 'category_id'); }
    public function measurementUnit() { return $this->belongsTo(MeasurementUnit::class, 'measurement_unit'); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    // Convenience accessor: get a single attribute value by key
    public function attr(string $key)
    {
        return $this->attributes_array()[$key] ?? null;
    }

    private function attributes_array(): array
    {
        return $this->attributes['attributes'] ?? ($this->getAttribute('attributes') ?? []);
    }

    public function weightedAverageCost(?int $locationId = null): float
    {
        $query = \App\Models\LocationStockLedger::where('product_id', $this->id)->where('status', 'fresh');
        if ($locationId) $query->where('location_id', $locationId);
        $qty = (float) (clone $query)->sum('quantity');
        $amt = (float) (clone $query)->sum('amount');
        return $qty > 0.001 ? round($amt / $qty, 4) : 0;
    }
}
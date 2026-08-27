<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeasurementUnit extends Model
{
    // No SoftDeletes — units are referenced by products and invoices.
    // Soft-deleting a unit while products still reference it causes
    // orphaned FKs. Hard guard with hasProducts() check in controller instead.

    protected $fillable = ['name', 'shortcode'];

    // ── Relationships ────────────────────────────────────────────────

    public function products()
    {
        return $this->hasMany(Product::class, 'measurement_unit', 'id');
    }

    // ── Helper ──────────────────────────────────────────────────────

    /**
     * Display label used in dropdowns across all modules.
     * e.g. "Pounds (lbs)"
     */
    public function getLabelAttribute(): string
    {
        return $this->name . ' (' . $this->shortcode . ')';
    }
}
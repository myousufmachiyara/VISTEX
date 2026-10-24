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
     * The "meter" unit, whatever it is called on this system (m, mtr, mtrs, meter, metre…).
     * Created as "Meter (m)" if there is none, so fabric lines and products never end up without a unit.
     */
    public static function meterId(): int
    {
        $names = ['m', 'mtr', 'mtrs', 'meter', 'meters', 'metre', 'metres', 'mt'];
        $id = static::query()->where(function ($q) use ($names) {
            $q->whereIn(\DB::raw('LOWER(TRIM(shortcode))'), $names)->orWhereIn(\DB::raw('LOWER(TRIM(name))'), $names);
        })->orderByRaw("CASE WHEN LOWER(TRIM(shortcode)) = 'm' THEN 0 ELSE 1 END")->value('id');
        return (int) ($id ?: static::create(['name' => 'Meter', 'shortcode' => 'm'])->id);
    }

    /**
     * Display label used in dropdowns across all modules.
     * e.g. "Pounds (lbs)"
     */
    public function getLabelAttribute(): string
    {
        return $this->name . ' (' . $this->shortcode . ')';
    }
}
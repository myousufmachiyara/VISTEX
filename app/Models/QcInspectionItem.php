<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QcInspectionItem extends Model
{
    public const DEFECTS = [
        'shade_variation' => 'Shade variation',
        'uneven_dyeing'   => 'Uneven dyeing / patches',
        'print_fault'     => 'Print fault / misregistration',
        'stains'          => 'Stains / spots',
        'holes'           => 'Holes / tears',
        'crease'          => 'Crease / crush marks',
        'width_short'     => 'Width short',
        'gsm_off'         => 'GSM off',
        'bowing'          => 'Bowing / skew',
        'hand_feel'       => 'Hand feel / finish',
        'other'           => 'Other',
    ];

    protected $fillable = [
        'qc_inspection_id', 'purchase_receiving_item_id', 'product_id', 'lot_no',
        'received_a', 'received_b', 'received_c', 'rate',
        'rolls', 'gross_kg', 'tare_kg', 'measured_meters', 'width_inch', 'expected_gsm',
        'final_a', 'final_b', 'final_c', 'reprocess_qty', 'shortage_qty', 'defects', 'defect_notes',
    ];

    protected $casts = [
        'defects' => 'array',
        'received_a' => 'float', 'received_b' => 'float', 'received_c' => 'float', 'rate' => 'float',
        'gross_kg' => 'float', 'tare_kg' => 'float', 'measured_meters' => 'float', 'width_inch' => 'float', 'expected_gsm' => 'float',
        'final_a' => 'float', 'final_b' => 'float', 'final_c' => 'float', 'reprocess_qty' => 'float', 'shortage_qty' => 'float',
    ];

    public function inspection()     { return $this->belongsTo(QcInspection::class, 'qc_inspection_id'); }
    public function product()        { return $this->belongsTo(Product::class); }
    public function receivingItem()  { return $this->belongsTo(PurchaseReceivingItem::class, 'purchase_receiving_item_id'); }

    public function getReceivedTotalAttribute(): float { return round($this->received_a + $this->received_b + $this->received_c, 3); }
    public function getAccountedAttribute(): float
    {
        return round($this->final_a + $this->final_b + $this->final_c + $this->reprocess_qty + $this->shortage_qty, 3);
    }

    public function getNetKgAttribute(): ?float
    {
        return $this->gross_kg === null ? null : round(max(0, $this->gross_kg - (float) $this->tare_kg), 3);
    }

    // Actual GSM from the kanta: net grams ÷ (meters × width in m)
    public function getActualGsmAttribute(): ?float
    {
        $m = $this->measured_meters ?: $this->received_total;
        if (!$this->net_kg || !$m || !$this->width_inch) return null;
        return round($this->net_kg * 1000 / ($m * $this->width_inch * 0.0254), 1);
    }

    public function getGsmVarianceAttribute(): ?float
    {
        if ($this->actual_gsm === null || !$this->expected_gsm) return null;
        return round(($this->actual_gsm - $this->expected_gsm) / $this->expected_gsm * 100, 1);
    }

    public function getDefectLabelsAttribute(): string
    {
        return collect($this->defects ?? [])->map(fn($d) => self::DEFECTS[$d] ?? $d)->implode(', ');
    }
}

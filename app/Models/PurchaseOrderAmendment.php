<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderAmendment extends Model
{
    protected $table = 'purchase_order_amendments';

    protected $fillable = [
        'purchase_order_id', 'challan_id', 'amendment_no', 'previous_values', 'new_values',
        'reason', 'requested_by', 'approved_by', 'approved_at', 'status', 'rejection_reason',
    ];

    protected $casts = [
        'previous_values' => 'array',
        'new_values'       => 'array',
        'approved_at'      => 'datetime',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function challan()       { return $this->belongsTo(Challan::class, 'challan_id'); }
    public function requestedBy()   { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver()      { return $this->belongsTo(User::class, 'approved_by'); }

    // Human-readable "field: old → new" lines, including item-level changes
    public function getChangeLinesAttribute(): array
    {
        $lines = [];
        $prev = $this->previous_values ?? [];
        foreach ($this->new_values ?? [] as $field => $new) {
            if ($field === 'items') {
                $prevItems = collect($prev['items'] ?? [])->keyBy('id');
                foreach ($new as $row) {
                    $old = $prevItems->get($row['id'], []);
                    $label = $old['label'] ?? ('Item #' . $row['id']);
                    foreach (['quantity' => 'Qty', 'rate' => 'Rate'] as $k => $name) {
                        if (array_key_exists($k, $row) && (float) ($old[$k] ?? 0) != (float) $row[$k]) {
                            $lines[] = "{$label} {$name}: " . ($old[$k] ?? '—') . " → {$row[$k]}";
                        }
                    }
                }
                continue;
            }
            $lines[] = ucwords(str_replace('_', ' ', $field)) . ': ' . ($prev[$field] ?? '—') . ' → ' . (is_scalar($new) ? $new : json_encode($new));
        }
        return $lines;
    }
}

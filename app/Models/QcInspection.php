<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QcInspection extends Model
{
    public const OPEN = 'Open';
    public const CLOSED = 'Closed';

    protected $fillable = [
        'qc_no', 'purchase_receiving_id', 'purchase_order_id', 'vendor_id', 'location_id', 'inspection_date', 'status',
        'claim_amount', 'claim_reason', 'shortage_amount', 'pdc_reduced_by', 'remarks',
        'created_by', 'updated_by', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'inspection_date' => 'date', 'closed_at' => 'datetime',
        'claim_amount' => 'decimal:2', 'shortage_amount' => 'decimal:2', 'pdc_reduced_by' => 'decimal:2',
    ];

    public function items()         { return $this->hasMany(QcInspectionItem::class); }
    public function receiving()     { return $this->belongsTo(PurchaseReceiving::class, 'purchase_receiving_id'); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function vendor()        { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function location()      { return $this->belongsTo(Location::class, 'location_id'); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }
    public function closer()        { return $this->belongsTo(User::class, 'closed_by'); }

    public function isOpen(): bool { return $this->status === self::OPEN; }

    public function getStatusBadgeAttribute(): string { return $this->isOpen() ? 'warning' : 'success'; }
}

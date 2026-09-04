<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderAmendment extends Model
{
    protected $table = 'purchase_order_amendments';

    protected $fillable = [
        'purchase_order_id', 'amendment_no', 'previous_values', 'new_values',
        'reason', 'requested_by', 'approved_by', 'approved_at', 'status', 'rejection_reason',
    ];

    protected $casts = [
        'previous_values' => 'array',
        'new_values'       => 'array',
        'approved_at'      => 'datetime',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function requestedBy()   { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver()      { return $this->belongsTo(User::class, 'approved_by'); }
}
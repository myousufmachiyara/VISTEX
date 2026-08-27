<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceiving extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_receivings';

    protected $fillable = [
        'receiving_no', 'purchase_order_id', 'location_id', 'receiving_date',
        'vendor_challan_no', 'amount', 'status', 'approved_by', 'approved_at',
        'rejection_reason', 'remarks', 'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'receiving_date' => 'date',
        'approved_at'    => 'datetime',
        'attachments'    => 'array',
        'amount'         => 'decimal:2',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function location()      { return $this->belongsTo(Location::class, 'location_id'); }
    public function items()         { return $this->hasMany(PurchaseReceivingItem::class, 'purchase_receiving_id'); }
    public function approver()      { return $this->belongsTo(User::class, 'approved_by'); }

    public function canBeApprovedBy(\App\Models\User $user): bool
    {
        return $this->purchaseOrder && $this->purchaseOrder->canBeApprovedBy($user);
    }
}
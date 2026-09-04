<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceiving extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_receivings';

    protected $fillable = [
        'receiving_no', 'purchase_order_id', 'challan_id', 'receiving_date',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
        'amount', 'remarks', 'created_by', 'updated_by',
        'is_final_receiving', 'yarn_consumed_meta', 'yarn_cost_amount', 'weaving_charge_amount',
    ];

    protected $casts = [
        'receiving_date'         => 'date',
        'approved_at'            => 'datetime',
        'amount'                 => 'decimal:2',
        'is_final_receiving'     => 'boolean',
        'yarn_consumed_meta'     => 'array',
        'yarn_cost_amount'       => 'decimal:2',
        'weaving_charge_amount'  => 'decimal:2',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function challan()       { return $this->belongsTo(Challan::class, 'challan_id'); }
    public function items()         { return $this->hasMany(PurchaseReceivingItem::class, 'purchase_receiving_id'); }
    public function approver()      { return $this->belongsTo(User::class, 'approved_by'); }

    public function canBeApprovedBy(User $user): bool
    {
        return $this->purchaseOrder && $this->purchaseOrder->canBeApprovedBy($user);
    }
}
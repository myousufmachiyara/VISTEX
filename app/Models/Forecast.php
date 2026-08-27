<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Forecast extends Model
{
    use SoftDeletes;

    protected $table = 'forecasts';

    protected $fillable = [
        'forecast_no', 'customer_id', 'product_id',
        'required_qty', 'stock_on_hand', 'on_order_qty', 'shortfall_qty',
        'required_by_date', 'remarks', 'status',
        'approved_by', 'approved_at', 'rejection_reason',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'required_qty'      => 'decimal:3',
        'stock_on_hand'      => 'decimal:3',
        'on_order_qty'       => 'decimal:3',
        'shortfall_qty'      => 'decimal:3',
        'required_by_date'   => 'date',
        'approved_at'        => 'datetime',
    ];

    public function customer()  { return $this->belongsTo(Customer::class, 'customer_id'); }
    public function product()   { return $this->belongsTo(Product::class, 'product_id'); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function approver()  { return $this->belongsTo(User::class, 'approved_by'); }

    public function scopePending($q)  { return $q->where('status', 'Pending'); }
    public function scopeApproved($q) { return $q->where('status', 'Approved'); }

    // Can this forecast still be linked from a new PO? Only if approved
    // and not yet fully consumed by existing POs.
    public function canBeLinkedToPo(): bool
    {
        return $this->status === 'Approved';
    }
}
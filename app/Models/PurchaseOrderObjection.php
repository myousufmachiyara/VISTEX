<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderObjection extends Model
{
    protected $table = 'purchase_order_objections';

    protected $fillable = ['purchase_order_id', 'remarks', 'status', 'raised_by', 'resolved_by', 'resolved_at'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function raisedBy()      { return $this->belongsTo(User::class, 'raised_by'); }
    public function resolvedBy()    { return $this->belongsTo(User::class, 'resolved_by'); }
}
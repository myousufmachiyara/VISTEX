<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Challan extends Model
{
    use SoftDeletes;

    protected $table = 'challans';

    protected $fillable = [
        'challan_no', 'purchase_order_id', 'vendor_challan_no',
        'received_date', 'challan_images', 'status', 'remarks',
        'received_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'received_date'  => 'date',
        'challan_images' => 'array',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function receivedBy()    { return $this->belongsTo(User::class, 'received_by'); }

    public function scopeAwaitingInspection($q) { return $q->where('status', 'AwaitingInspection'); }

    // Which category in-charges should see this on their dashboard
    public function scopeForCategoryIncharge($q, \App\Models\User $user)
    {
        if ($user->hasRole('superadmin')) return $q;

        return $q->whereHas('purchaseOrder.category.incharges', fn($q2) => $q2->where('user_id', $user->id));
    }
}
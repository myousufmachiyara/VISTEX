<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Challan extends Model
{
    use SoftDeletes;

    protected $table = 'challans';

    protected $fillable = [
        'challan_no', 'entry_type', 'purchase_order_id', 'vendor_challan_no', 'vendor_id',
        'received_date', 'challan_images', 'status', 'remarks',
        'received_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'received_date'  => 'date',
        'challan_images' => 'array',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function vendor()        { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function receivedBy()    { return $this->belongsTo(User::class, 'received_by'); }
    public function directItems()   { return $this->hasMany(ChallanDirectItem::class, 'challan_id'); }

    // For PO-based challans, resolve the vendor through the PO;
    // for direct entries, use the vendor_id column directly.
    public function getDisplayVendorAttribute()
    {
        return $this->entry_type === 'direct' ? $this->vendor : ($this->purchaseOrder->vendor ?? null);
    }

    public function scopeAwaitingInspection($q) { return $q->where('status', 'AwaitingInspection'); }

    public function scopeForCategoryIncharge($q, User $user)
    {
        if ($user->hasRole('superadmin')) return $q;

        return $q->where(function ($q2) use ($user) {
            $q2->whereHas('purchaseOrder.category.incharges', fn($q3) => $q3->where('user_id', $user->id))
               ->orWhere('entry_type', 'direct'); // direct entries visible to all in-charges for now — refine later if needed
        });
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'order_no', 'revision_no', 'vendor_id', 'product_category_id',
        'from_location_id', 'drop_off_location_id',
        'order_date', 'expected_date',
        'gst_applicable', 'tax_id', 'gst_rate',
        'subtotal', 'gst_amount', 'total_amount',
        'status', 'locked_by', 'remarks', 'attachments',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'order_date'      => 'date',
        'expected_date'   => 'date',
        'gst_applicable'  => 'boolean',
        'gst_rate'        => 'decimal:2',
        'subtotal'        => 'decimal:2',
        'gst_amount'      => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'revision_no'     => 'integer',
        'attachments'     => 'array',
    ];

    public function vendor()          { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function category()        { return $this->belongsTo(ProductCategory::class, 'product_category_id'); }
    public function fromLocation()    { return $this->belongsTo(Location::class, 'from_location_id'); }
    public function dropOffLocation() { return $this->belongsTo(Location::class, 'drop_off_location_id'); }
    public function forecast()        { return $this->belongsTo(Forecast::class, 'forecast_id'); }
    public function tax()             { return $this->belongsTo(TaxMaster::class, 'tax_id'); }
    public function creator()         { return $this->belongsTo(User::class, 'locked_by'); }
    public function items()           { return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id'); }

    // ── Visibility: superadmin sees all; others see only their own
    // creations, POs at a location they manage, or POs in a category
    // they're in-charge of.
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('superadmin')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('locked_by', $user->id)
              ->orWhereHas('dropOffLocation', fn($q2) => $q2->where('in_charge_user_id', $user->id))
              ->orWhereHas('category.incharges', fn($q2) => $q2->where('user_id', $user->id));
        });
    }

    public function canBeEditedBy(User $user): bool
    {
        return $user->hasRole('superadmin') || $this->locked_by === $user->id;
    }

    public function isReceivableBy(User $user): bool
    {
        if ($user->hasRole('superadmin') || $user->hasRole('gatekeeper')) return true;
        return $this->dropOffLocation && $this->dropOffLocation->in_charge_user_id === $user->id;
    }

    public function canBeApprovedBy(User $user): bool
    {
        if ($user->hasRole('superadmin')) return true;

        return CategoryIncharge::where('product_category_id', $this->product_category_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function getQuantityOrderedAttribute(): float
    {
        return (float) $this->items->sum('quantity');
    }

    public function getQuantityReceivedAttribute(): float
    {
        return (float) $this->items->sum('quantity_received');
    }
    public function objections()      { return $this->hasMany(PurchaseOrderObjection::class, 'purchase_order_id'); }
    public function openObjections()  { return $this->hasMany(PurchaseOrderObjection::class, 'purchase_order_id')->where('status', 'Open'); }
}
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Challan extends Model
{
    use SoftDeletes;
    protected $table = 'challans';

    // Gate -> incharge review lifecycle
    public const AWAITING   = 'AwaitingInspection';
    public const AMENDING   = 'AwaitingAmendment';
    public const ACCEPTED   = 'Accepted';
    public const OBJECTION  = 'AcceptedWithObjection';
    public const REJECTED   = 'Rejected';
    public const PROCESSED  = 'Processed'; // legacy value from the old GRN flow

    public const STATUS_LABELS = [
        self::AWAITING  => 'Awaiting Inspection',
        self::AMENDING  => 'Awaiting Amendment',
        self::ACCEPTED  => 'Accepted',
        self::OBJECTION => 'Accepted with Objection',
        self::REJECTED  => 'Rejected',
        self::PROCESSED => 'Processed',
    ];

    public const DECISIONS = [
        'accept'                => 'Accept — no objection',
        'accept_with_objection' => 'Accept with objection',
        'amend'                 => 'Request PO amendment',
        'reject'                => 'Reject consignment',
    ];

    protected $fillable = [
        'challan_no', 'entry_type', 'category_id', 'purchase_order_id', 'vendor_challan_no', 'vendor_id', 'direct_vendor_name',
        'vehicle_no', 'driver_name', 'driver_contact',
        'received_date', 'challan_images', 'status',
        'decision', 'decision_remarks', 'amendment_id', 'objection_id',
        'has_objection', 'objection_remarks', 'objection_voice_note',
        'reviewed_by', 'reviewed_at', 'payable_vendor_id', 'payable_account_id', 'paid_from_account_id',
        'remarks', 'received_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'received_date' => 'date', 'reviewed_at' => 'datetime',
        'challan_images' => 'array', 'has_objection' => 'boolean',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function category()      { return $this->belongsTo(ProductCategory::class, 'category_id'); }
    public function vendor()        { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function receivedBy()    { return $this->belongsTo(User::class, 'received_by'); }
    public function reviewedBy()    { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function directItems()   { return $this->hasMany(ChallanDirectItem::class, 'challan_id'); }
    public function items()         { return $this->hasMany(ChallanItem::class, 'challan_id'); }
    public function receiving()     { return $this->hasOne(PurchaseReceiving::class, 'challan_id')->latestOfMany(); }
    public function amendment()     { return $this->belongsTo(PurchaseOrderAmendment::class, 'amendment_id'); }
    public function objection()     { return $this->belongsTo(PurchaseOrderObjection::class, 'objection_id'); }

    // Vendor name for both PO-based and no-PO (typed) challans
    public function getDisplayVendorNameAttribute(): string
    {
        if ($this->entry_type === 'direct') return $this->vendor->name ?? ($this->direct_vendor_name ?? '');
        return $this->purchaseOrder->vendor->name ?? '';
    }

    // kept for existing Blades that still use ->display_vendor->name
    public function getDisplayVendorAttribute()
    {
        return $this->entry_type === 'direct' ? $this->vendor : ($this->purchaseOrder->vendor ?? null);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::ACCEPTED, self::PROCESSED => 'success',
            self::OBJECTION => 'warning text-dark',
            self::AMENDING  => 'info text-dark',
            self::REJECTED  => 'danger',
            default         => 'secondary',
        };
    }

    public function isAwaitingReview(): bool { return $this->status === self::AWAITING; }

    public function scopeAwaitingInspection($q) { return $q->where('status', self::AWAITING); }

    public function scopeForCategoryIncharge($q, User $user)
    {
        if ($user->hasRole('superadmin')) return $q;
        return $q->where(function ($q2) use ($user) {
            $q2->whereHas('purchaseOrder.category.incharges', fn($q3) => $q3->where('user_id', $user->id))
               ->orWhereHas('category.incharges', fn($q3) => $q3->where('user_id', $user->id));
        });
    }

    // Gatekeepers see what they logged; incharges see their categories
    public function scopeVisibleTo($q, User $user)
    {
        if ($user->hasRole('superadmin')) return $q;
        return $q->where(function ($q2) use ($user) {
            $q2->where('received_by', $user->id)
               ->orWhereHas('purchaseOrder.category.incharges', fn($q3) => $q3->where('user_id', $user->id))
               ->orWhereHas('category.incharges', fn($q3) => $q3->where('user_id', $user->id));
        });
    }

    public function hasTransportDetails(): bool
    {
        return (bool) ($this->vehicle_no || $this->driver_name || $this->driver_contact);
    }

    // Editable until the incharge decides: by whoever logged it, the category incharge, or a superadmin.
    public function canBeEditedBy(?User $user): bool
    {
        if (!$user || $this->status !== self::AWAITING) return false;
        if ($user->hasRole('superadmin') || (int) $this->received_by === (int) $user->id) return true;
        return $this->canBeReviewedBy($user);
    }

    public function canBeReviewedBy(User $user): bool
    {
        if ($user->hasRole('superadmin')) return true;
        $categoryId = $this->category_id ?? $this->purchaseOrder?->product_category_id;
        return $categoryId && CategoryIncharge::where('product_category_id', $categoryId)->where('user_id', $user->id)->exists();
    }
}

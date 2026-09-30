<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;
    protected $table = 'purchase_orders';

    protected $fillable = [
        'order_no', 'type', 'revision_no', 'vendor_id', 'product_category_id', 'service_type_id',
        'job_id', 'program', 'fabric_specs',
        'from_location_id', 'drop_off_location_id',
        'order_date', 'expected_date',
        'broker_id', 'broker_commission_amount',
        'payment_term_type', 'payment_term_days', 'payment_term_note',
        'gst_applicable', 'tax_id', 'gst_rate',
        'subtotal', 'gst_amount', 'total_amount',
        'status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'rejection_reason', 'locked_by',

        // Weaving — product links
        'warp_product_id', 'weft_product_id', 'greige_product_id',

        // Weaving — raw formula inputs (stored via weavingFieldsPayload())
        'reed', 'reed_count', 'warp_count', 'weft_count', 'pick', 'width',
        'total_meters_required', 'rate_per_pick', 'sizing_lbs', 'warping',
        'warp_conversion_pct', 'weft_conversion_pct',
        'warp_yarn_cost_price', 'weft_yarn_cost_price',

        // Weaving — derived outputs (stored via $calc from CpoFormulaService)
        'reed_space', 'warp_gsm', 'weft_gsm', 'gsm', 'gsm_kg',
        'warp_required_lbs', 'weft_required_lbs',
        'warp_consumption', 'weft_consumption', 'total_yarn_weight_consumed','total_yarn_required',
        'warp_yarn_rate', 'weft_yarn_rate', 'total_yarn_cost_per_meter',
        'weaving_cost_per_meter', 'sizing_rate_per_meter', 'weaving_per_meter',
        'fabric_cost', 'weaving_cost', 'item_name',

        'is_final_receiving_done',
        'forecast_id', 'remarks', 'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date', 'expected_date' => 'date', 'approved_at' => 'datetime', 'submitted_at' => 'datetime',
        'gst_applicable' => 'boolean', 'is_final_receiving_done' => 'boolean',
        'attachments' => 'array', 'fabric_specs' => 'array',

        'broker_commission_amount' => 'decimal:2',
        'subtotal' => 'decimal:2', 'gst_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'gst_rate' => 'decimal:2',

        // Weaving inputs
        'reed' => 'decimal:4', 'reed_count' => 'decimal:4', 'warp_count' => 'decimal:4', 'weft_count' => 'decimal:4',
        'pick' => 'decimal:4', 'width' => 'decimal:4', 'total_meters_required' => 'decimal:3',
        'rate_per_pick' => 'decimal:2', 'sizing_lbs' => 'decimal:4', 'warping' => 'decimal:4',
        'warp_conversion_pct' => 'decimal:2', 'weft_conversion_pct' => 'decimal:2',
        'warp_yarn_cost_price' => 'decimal:4', 'weft_yarn_cost_price' => 'decimal:4','total_yarn_required' => 'decimal:4',
        'warp_required_lbs' => 'decimal:4', 'weft_required_lbs' => 'decimal:4',
        // Weaving outputs
        'reed_space' => 'decimal:2', 'warp_gsm' => 'decimal:2', 'weft_gsm' => 'decimal:2',
        'gsm' => 'decimal:2', 'gsm_kg' => 'decimal:4',
        'warp_consumption' => 'decimal:4', 'weft_consumption' => 'decimal:4', 'total_yarn_weight_consumed' => 'decimal:4',
        'warp_yarn_rate' => 'decimal:2', 'weft_yarn_rate' => 'decimal:2', 'total_yarn_cost_per_meter' => 'decimal:2',
        'weaving_cost_per_meter' => 'decimal:2', 'sizing_rate_per_meter' => 'decimal:2', 'weaving_per_meter' => 'decimal:2',
        'fabric_cost' => 'decimal:2', 'weaving_cost' => 'decimal:2',
    ];

    public const TYPES = ['purchase' => 'Purchase', 'weaving' => 'Weaving', 'processing' => 'Processing'];

    // Lifecycle: Draft -> Pending (requested) -> Approved -> Issued -> PartiallyReceived -> Received
    //            Pending -> Rejected -> (edited) -> Draft
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PENDING = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_ISSUED = 'Issued';
    public const STATUS_PARTIAL = 'PartiallyReceived';
    public const STATUS_RECEIVED = 'Received';
    public const STATUS_REJECTED = 'Rejected';

    public const STATUSES = [
        self::STATUS_DRAFT    => 'Draft',
        self::STATUS_PENDING  => 'Pending Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_ISSUED   => 'Issued',
        self::STATUS_PARTIAL  => 'Partially Received',
        self::STATUS_RECEIVED => 'Received',
        self::STATUS_REJECTED => 'Rejected',
    ];

    // Statuses in which the creator may still change the PO
    public const EDITABLE_STATUSES = [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_REJECTED];

    public function vendor()          { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function category()        { return $this->belongsTo(ProductCategory::class, 'product_category_id'); }
    public function serviceType()     { return $this->belongsTo(ServiceType::class, 'service_type_id'); }
    public function job()             { return $this->belongsTo(Job::class, 'job_id'); }
    public function fromLocation()    { return $this->belongsTo(Location::class, 'from_location_id'); }
    public function dropOffLocation() { return $this->belongsTo(Location::class, 'drop_off_location_id'); }
    public function broker()          { return $this->belongsTo(Broker::class, 'broker_id'); }
    public function tax()             { return $this->belongsTo(TaxMaster::class, 'tax_id'); }
    public function forecast()        { return $this->belongsTo(Forecast::class, 'forecast_id'); }
    public function approver()        { return $this->belongsTo(User::class, 'approved_by'); }
    public function submitter()       { return $this->belongsTo(User::class, 'submitted_by'); }
    public function creator()         { return $this->belongsTo(User::class, 'locked_by'); }
    public function items()           { return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id'); }
    public function terms()           { return $this->hasMany(PurchaseOrderTerm::class, 'purchase_order_id'); }
    public function objections()      { return $this->hasMany(PurchaseOrderObjection::class, 'purchase_order_id'); }
    public function openObjections()  { return $this->hasMany(PurchaseOrderObjection::class, 'purchase_order_id')->where('status', 'Open'); }
    public function amendments()      { return $this->hasMany(PurchaseOrderAmendment::class, 'purchase_order_id')->orderByDesc('amendment_no'); }

    public function warpProduct()   { return $this->belongsTo(Product::class, 'warp_product_id'); }
    public function weftProduct()   { return $this->belongsTo(Product::class, 'weft_product_id'); }
    public function greigeProduct() { return $this->belongsTo(Product::class, 'greige_product_id'); }

    public function issuances()         { return $this->hasMany(Issuance::class, 'purchase_order_id'); }
    public function challans()          { return $this->hasMany(Challan::class, 'purchase_order_id'); }
    public function processingIssues()  { return $this->hasMany(ProcessingIssue::class, 'purchase_order_id'); }
    public function receivings()        { return $this->hasMany(PurchaseReceiving::class, 'purchase_order_id'); }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('superadmin')) return $query;
        return $query->where(function ($q) use ($user) {
            $q->where('locked_by', $user->id)
              ->orWhere(function ($q2) use ($user) {
                  // A Draft is private to its creator until it is submitted
                  $q2->where('status', '!=', self::STATUS_DRAFT)
                     ->where(function ($q3) use ($user) {
                         $q3->whereHas('dropOffLocation', fn($l) => $l->where('in_charge_user_id', $user->id))
                            ->orWhereHas('category.incharges', fn($c) => $c->where('user_id', $user->id));
                     });
              });
        });
    }

    // Anything the gate / receiving side may act on
    public function scopeOpenForReceiving($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_ISSUED, self::STATUS_PARTIAL]);
    }

    public function isOwnedBy(User $user): bool
    {
        return $user->hasRole('superadmin') || (int) $this->locked_by === (int) $user->id;
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->isOwnedBy($user) && in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function canBeSubmittedBy(User $user): bool
    {
        return $this->isOwnedBy($user) && $this->status === self::STATUS_DRAFT;
    }

    // Pull a Pending PO back to Draft before the approver has acted on it
    public function canBeRecalledBy(User $user): bool
    {
        return $this->isOwnedBy($user) && $this->status === self::STATUS_PENDING;
    }

    public function canBeDeletedBy(User $user): bool
    {
        return $this->canBeEditedBy($user);
    }

    public function canBeApprovedBy(User $user): bool { return $user->hasRole('superadmin'); }

    public function isInchargeOrAdmin(User $user): bool
    {
        if ($user->hasRole('superadmin')) return true;
        return CategoryIncharge::where('product_category_id', $this->product_category_id)->where('user_id', $user->id)->exists();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    // Bootstrap badge class, shared by index/show so colours stay consistent
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT    => 'secondary',
            self::STATUS_PENDING  => 'warning text-dark',
            self::STATUS_APPROVED => 'primary',
            self::STATUS_ISSUED   => 'info text-dark',
            self::STATUS_PARTIAL  => 'warning text-dark',
            self::STATUS_RECEIVED => 'success',
            self::STATUS_REJECTED => 'danger',
            default               => 'light text-dark',
        };
    }

    public function isReceivableBy(User $user): bool
    {
        if ($user->hasRole('superadmin') || $user->hasRole('gatekeeper')) return true;
        return $this->dropOffLocation && $this->dropOffLocation->in_charge_user_id === $user->id;
    }

    // Total quantity issued against this PO across all issuances (yarn for weaving, greige for processing)
    public function getIssuedTotalAttribute(): float
    {
        return (float) Issuance::where('purchase_order_id', $this->id)
            ->join('issuance_items', 'issuances.id', '=', 'issuance_items.issuance_id')
            ->whereNull('issuances.deleted_at')
            ->sum('issuance_items.quantity');
    }

    // Kept for existing callers (reports, receiving screens)
    public function getYarnIssuedTotalAttribute(): float
    {
        return $this->issued_total;
    }

    public function getEffective(string $field)
    {
        $latest = $this->amendments()->where('status', 'Approved')->first();
        if ($latest && array_key_exists($field, $latest->new_values)) return $latest->new_values[$field];
        return $this->{$field};
    }
}
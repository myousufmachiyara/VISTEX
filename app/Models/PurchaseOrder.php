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
        'broker_id', 'broker_commission_amount', // ← 'broker_commission_type'/'broker_commission_value' REMOVED (Fix 3 dropped these)
        'payment_term_type', 'payment_term_days', 'payment_term_note',
        'gst_applicable', 'tax_id', 'gst_rate',
        'subtotal', 'gst_amount', 'total_amount',
        'status', 'approved_by', 'approved_at', 'rejection_reason', 'locked_by',
        'warp_product_id', 'weft_product_id', 'greige_product_id',
        'warp_count', 'weft_count', 'reed_count', 'pick', 'width', 'total_meters_required',
        'rate_per_pick', 'sizing_lbs',
        'reed', 'reed_space', 'warping',                                  // ← NEW
        'warp_conversion_pct', 'weft_conversion_pct',                      // ← labels renamed to "Shrinkage %" but field names unchanged (per last message)
        'warp_shrinkage_pct', 'weft_shrinkage_pct',                        // ← keep only if these still exist as separate DB columns from the earlier fix; see note below
        'warp_yarn_cost_price', 'weft_yarn_cost_price',                    // ← NEW
        'warp_yarn_rate', 'weft_yarn_rate',                                // ← NEW (computed, stored)
        'weaving_cost_per_meter', 'sizing_rate_per_meter', 'actual_cost_per_meter', // ← NEW (computed, stored)
        'gsm', 'total_greige_qty_required', 'total_yarn_weight_consumed',
        'weaving_cost', 'item_name',
        'is_final_receiving_done',
        'forecast_id', 'remarks', 'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date', 'expected_date' => 'date', 'approved_at' => 'datetime',
        'gst_applicable' => 'boolean', 'is_final_receiving_done' => 'boolean',
        'attachments' => 'array', 'fabric_specs' => 'array',
        'reed' => 'decimal:4', 'reed_space' => 'decimal:4', 'warping' => 'decimal:4',
        'warp_yarn_cost_price' => 'decimal:4', 'weft_yarn_cost_price' => 'decimal:4',
        'warp_yarn_rate' => 'decimal:4', 'weft_yarn_rate' => 'decimal:4',
        'weaving_cost_per_meter' => 'decimal:4', 'sizing_rate_per_meter' => 'decimal:4',
        'actual_cost_per_meter' => 'decimal:4',
    ];

    public const TYPES = ['purchase' => 'Purchase', 'weaving' => 'Weaving', 'processing' => 'Processing'];

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
    public function creator()         { return $this->belongsTo(User::class, 'locked_by'); }
    public function items()           { return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id'); }
    public function terms()           { return $this->hasMany(PurchaseOrderTerm::class, 'purchase_order_id'); }
    public function objections()      { return $this->hasMany(PurchaseOrderObjection::class, 'purchase_order_id'); }
    public function openObjections()  { return $this->hasMany(PurchaseOrderObjection::class, 'purchase_order_id')->where('status', 'Open'); }
    public function amendments()      { return $this->hasMany(PurchaseOrderAmendment::class, 'purchase_order_id')->orderByDesc('amendment_no'); }

    public function warpProduct()   { return $this->belongsTo(Product::class, 'warp_product_id'); }
    public function weftProduct()   { return $this->belongsTo(Product::class, 'weft_product_id'); }
    public function greigeProduct() { return $this->belongsTo(Product::class, 'greige_product_id'); }

    public function yarnIssues()        { return $this->hasMany(YarnIssue::class, 'purchase_order_id'); }
    public function processingIssues()  { return $this->hasMany(ProcessingIssue::class, 'purchase_order_id'); }
    public function receivings()        { return $this->hasMany(PurchaseReceiving::class, 'purchase_order_id'); }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('superadmin')) return $query;
        return $query->where(function ($q) use ($user) {
            $q->where('locked_by', $user->id)
              ->orWhereHas('dropOffLocation', fn($q2) => $q2->where('in_charge_user_id', $user->id))
              ->orWhereHas('category.incharges', fn($q2) => $q2->where('user_id', $user->id));
        });
    }

    public function canBeEditedBy(User $user): bool
    {
        return ($user->hasRole('superadmin') || $this->locked_by === $user->id) && $this->status === 'Pending';
    }

    public function canBeApprovedBy(User $user): bool { return $user->hasRole('superadmin'); }

    public function isReceivableBy(User $user): bool
    {
        if ($user->hasRole('superadmin') || $user->hasRole('gatekeeper')) return true;
        return $this->dropOffLocation && $this->dropOffLocation->in_charge_user_id === $user->id;
    }

    public function getYarnIssuedTotalAttribute(): float
    {
        return (float) $this->yarnIssues()
            ->join('yarn_issue_items', 'yarn_issues.id', '=', 'yarn_issue_items.yarn_issue_id')
            ->sum('yarn_issue_items.quantity');
    }

    public function getEffective(string $field)
    {
        $latest = $this->amendments()->where('status', 'Approved')->first();
        if ($latest && array_key_exists($field, $latest->new_values)) return $latest->new_values[$field];
        return $this->{$field};
    }
}
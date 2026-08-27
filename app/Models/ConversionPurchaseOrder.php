<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversionPurchaseOrder extends Model
{
    use SoftDeletes;

    protected $table = 'conversion_purchase_orders';

    protected $fillable = [
        'cpo_no', 'vendor_id', 'warp_product_id', 'weft_product_id', 'greige_product_id', 'forecast_id',
        'warp_count', 'weft_count', 'reed_count', 'pick', 'width', 'total_meters_required',
        'rate_per_pick', 'sizing_lbs', 'warp_conversion_pct',
        'gsm', 'warp_consumption', 'weft_consumption', 'total_greige_qty_required', 'total_yarn_weight_consumed',
        'rate_per_meter', 'sizing_per_meter', 'weaving_rate', 'weaving_cost',
        'gst_applicable', 'tax_id', 'gst_rate', 'gst_amount', 'net_amount',
        'item_name', 'po_date', 'status', 'remarks', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'po_date'         => 'date',
        'gst_applicable'  => 'boolean',
    ];

    public function vendor()        { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function warpProduct()   { return $this->belongsTo(Product::class, 'warp_product_id'); }
    public function weftProduct()   { return $this->belongsTo(Product::class, 'weft_product_id'); }
    public function greigeProduct() { return $this->belongsTo(Product::class, 'greige_product_id'); }
    public function forecast()      { return $this->belongsTo(Forecast::class, 'forecast_id'); }
    public function tax()           { return $this->belongsTo(TaxMaster::class, 'tax_id'); }
    public function greigeReceives(){ return $this->hasMany(GreigeReceive::class, 'cpo_id'); }

    public function scopeActive($q) { return $q->where('status', 'Active'); }

    // Sum of everything issued so far (populated once yarn_issues/yarn_issue_items exist)
    public function getYarnIssuedTotalAttribute(): float
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('yarn_issues')) return 0;

        return (float) \App\Models\YarnIssue::where('cpo_id', $this->id)
            ->join('yarn_issue_items', 'yarn_issues.id', '=', 'yarn_issue_items.yarn_issue_id')
            ->sum('yarn_issue_items.quantity');
    }
}
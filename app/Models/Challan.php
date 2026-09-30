<?php // app/Models/Challan.php  (full replacement)
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Challan extends Model
{
    use SoftDeletes;
    protected $table = 'challans';

    protected $fillable = [
        'challan_no', 'entry_type', 'category_id', 'purchase_order_id', 'vendor_challan_no', 'vendor_id', 'direct_vendor_name',
        'received_date', 'challan_images', 'status',
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

    public function scopeAwaitingInspection($q) { return $q->where('status', 'AwaitingInspection'); }

    public function scopeForCategoryIncharge($q, User $user)
    {
        if ($user->hasRole('superadmin')) return $q;
        return $q->where(function ($q2) use ($user) {
            $q2->whereHas('purchaseOrder.category.incharges', fn($q3) => $q3->where('user_id', $user->id))
               ->orWhereHas('category.incharges', fn($q3) => $q3->where('user_id', $user->id));
        });
    }
}
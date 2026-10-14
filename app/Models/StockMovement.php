<?php
// app/Models/StockMovement.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock transfer with a gate pass:
 *   Pending → Approved (gate pass issued) → Dispatched (gate verified exit, stock in transit) → Received
 * Vendor → warehouse skips our gate: approval dispatches it straight away.
 */
class StockMovement extends Model
{
    use SoftDeletes;

    protected $table = 'stock_movements';

    public const PENDING    = 'Pending';
    public const APPROVED   = 'Approved';
    public const DISPATCHED = 'Dispatched';
    public const RECEIVED   = 'Received';
    public const REJECTED   = 'Rejected';

    public const STATUS_LABELS = [
        self::PENDING    => 'Pending Approval',
        self::APPROVED   => 'Gate Pass Issued',
        self::DISPATCHED => 'In Transit',
        self::RECEIVED   => 'Received',
        self::REJECTED   => 'Rejected',
    ];

    protected $fillable = [
        'movement_no', 'movement_type', 'from_location_id', 'to_location_id', 'purchase_order_id', 'lot_no',
        'movement_date', 'status', 'approved_by', 'approved_at', 'rejection_reason',
        'remarks', 'attachments', 'vehicle_no', 'driver_name', 'driver_contact', 'gate_images', 'gate_remarks',
        'dispatched_by', 'dispatched_at', 'received_by', 'received_at', 'receive_remarks',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'approved_at'   => 'datetime',
        'dispatched_at' => 'datetime',
        'received_at'   => 'datetime',
        'attachments'   => 'array',
        'gate_images'   => 'array',
    ];

    public const TYPES = [
        'warehouse_to_warehouse' => 'Warehouse → Warehouse',
        'warehouse_to_vendor'    => 'Warehouse → Vendor / Mill',
        'vendor_to_warehouse'    => 'Vendor / Mill → Warehouse',
    ];

    public function fromLocation()  { return $this->belongsTo(Location::class, 'from_location_id'); }
    public function toLocation()    { return $this->belongsTo(Location::class, 'to_location_id'); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function items()         { return $this->hasMany(StockMovementItem::class, 'stock_movement_id'); }
    public function approver()      { return $this->belongsTo(User::class, 'approved_by'); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }
    public function dispatcher()    { return $this->belongsTo(User::class, 'dispatched_by'); }
    public function receiver()      { return $this->belongsTo(User::class, 'received_by'); }

    public function getStatusLabelAttribute(): string { return self::STATUS_LABELS[$this->status] ?? $this->status; }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::APPROVED   => 'info text-dark',
            self::DISPATCHED => 'primary',
            self::RECEIVED   => 'success',
            self::REJECTED   => 'danger',
            default          => 'warning text-dark',
        };
    }

    // Goods leave through our own gate unless they start at a vendor's location
    public function needsGateExit(): bool { return $this->movement_type !== 'vendor_to_warehouse'; }

    // The mill gives its own lot # when goods arrive at a vendor location
    public function needsMillLot(): bool { return $this->movement_type === 'warehouse_to_vendor'; }

    // Approval: source location's in-charge, or superadmin
    public function canBeApprovedBy(User $user): bool
    {
        if ($user->hasRole('superadmin')) return true;
        return $this->fromLocation && (int) $this->fromLocation->in_charge_user_id === (int) $user->id;
    }

    // Receiving: destination location's in-charge, or superadmin
    public function canBeReceivedBy(User $user): bool
    {
        if ($user->hasRole('superadmin')) return true;
        return $this->toLocation && (int) $this->toLocation->in_charge_user_id === (int) $user->id;
    }

    public function scopeAwaitingGate($q) { return $q->where('status', self::APPROVED); }
}

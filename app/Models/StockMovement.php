<?php
// app/Models/StockMovement.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use SoftDeletes;

    protected $table = 'stock_movements';

    protected $fillable = [
        'movement_no', 'movement_type', 'from_location_id', 'to_location_id', 'lot_no',
        'movement_date', 'status', 'approved_by', 'approved_at', 'rejection_reason',
        'remarks', 'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'approved_at'   => 'datetime',
        'attachments'   => 'array',
    ];

    public const TYPES = [
        'warehouse_to_warehouse' => 'Warehouse → Warehouse',
        'warehouse_to_vendor'    => 'Warehouse → Vendor',
        'vendor_to_warehouse'    => 'Vendor → Warehouse',
    ];

    public function fromLocation() { return $this->belongsTo(Location::class, 'from_location_id'); }
    public function toLocation()   { return $this->belongsTo(Location::class, 'to_location_id'); }
    public function items()        { return $this->hasMany(StockMovementItem::class, 'stock_movement_id'); }
    public function approver()     { return $this->belongsTo(User::class, 'approved_by'); }

    // Approval: destination location's in-charge, or superadmin
    public function canBeApprovedBy(User $user): bool
    {
        if ($user->hasRole('superadmin')) return true;
        return $this->toLocation && $this->toLocation->in_charge_user_id === $user->id;
    }
}
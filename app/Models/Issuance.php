<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Material issued out of our warehouse against a document.
 * Replaces the old Yarn Issue (same table, renamed — ids preserved).
 */
class Issuance extends Model
{
    use SoftDeletes;

    protected $table = 'issuances';

    // 'active' => false are the red (client-pending) flows: visible, not usable yet
    public const TYPES = [
        'yarn_weaving' => [
            'label' => 'Yarn for Weaving', 'against' => 'Weaving PO', 'po_type' => 'weaving', 'active' => true,
            'help'  => 'Yarn leaves our warehouse for the weaving mill and is held as Yarn-in-Process until greige is received.',
        ],
        'greige_processing' => [
            'label' => 'Greige for Processing', 'against' => 'Processing PO', 'po_type' => 'processing', 'active' => true,
            'help'  => 'The mill\'s location in-charge issues greige already at the mill (sent by Stock Movement, under the mill\'s lot #) to this Processing PO. It is then "in process" for the PO.',
        ],
        'greige_sale' => [
            'label' => 'Greige against Sale Order', 'against' => 'Sale Order', 'po_type' => null, 'active' => false,
            'help'  => 'Locked — pending client discussion.',
        ],
        'fabric_reprocess' => [
            'label' => 'Finish Fabric for Reprocess', 'against' => 'Reprocess PO', 'po_type' => null, 'active' => false,
            'help'  => 'Locked — pending client discussion.',
        ],
    ];

    protected $fillable = [
        'issue_no', 'issue_type', 'purchase_order_id', 'job_id', 'vendor_id',
        'source_location_id', 'destination_location_id',
        'issue_date', 'total_quantity', 'total_amount', 'remarks', 'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date'     => 'date',
        'attachments'    => 'array',
        'total_quantity' => 'decimal:3',
        'total_amount'   => 'decimal:2',
    ];

    public function purchaseOrder()       { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function job()                 { return $this->belongsTo(Job::class, 'job_id'); }
    public function vendor()              { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function sourceLocation()      { return $this->belongsTo(Location::class, 'source_location_id'); }
    public function destinationLocation() { return $this->belongsTo(Location::class, 'destination_location_id'); }
    public function items()               { return $this->hasMany(IssuanceItem::class, 'issuance_id'); }
    public function creator()             { return $this->belongsTo(User::class, 'created_by'); }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->issue_type]['label'] ?? $this->issue_type;
    }

    public static function activeTypes(): array
    {
        return array_filter(self::TYPES, fn($t) => $t['active']);
    }

    // Issued before greige was issued at the mill: those sent greige straight from our warehouse
    public function isWarehouseToMill(): bool
    {
        return $this->issue_type === 'greige_processing' && $this->destination_location_id !== null;
    }

    // Greige for processing is issued by the mill location's in-charge (or a superadmin)
    public static function canIssueAt(?Location $location, User $user): bool
    {
        if ($user->hasRole('superadmin')) return true;
        return $location && (int) $location->in_charge_user_id === (int) $user->id;
    }
}

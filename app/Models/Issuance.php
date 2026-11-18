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
        'fabric_cutting' => [
            'label' => 'Processed Fabric for Cutting', 'against' => 'Customer Job', 'po_type' => null, 'active' => true,
            'help'  => 'QC-passed processed fabric is issued to the cutting department against an open customer job. It stays in the warehouse as "issued for cutting" and only this fabric can be cut. Quantities are in yards (stock is kept in meters).',
        ],
        'greige_sale' => [
            'label' => 'Greige against Sale Order', 'against' => 'Sale Order', 'po_type' => null, 'active' => false,
            'help'  => 'Done from Sales → Sale Orders: each delivery goes out on a gate pass.',
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
        'vehicle_no', 'driver_name', 'driver_contact', 'no_of_bags', 'prepared_by_name', 'received_by_name',
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

    public const METERS_PER_YARD = 0.9144;
    public static function toYards(float $meters): float { return round($meters / self::METERS_PER_YARD, 3); }
    public static function toMeters(float $yards): float { return round($yards * self::METERS_PER_YARD, 3); }

    public function isForCutting(): bool { return $this->issue_type === 'fabric_cutting'; }

    // Who / what the issuance is against: the PO (mill) or the customer job
    public function getAgainstNoAttribute(): ?string
    {
        return $this->isForCutting() ? $this->job?->job_no : $this->purchaseOrder?->order_no;
    }
    public function getIssuedToAttribute(): string
    {
        return $this->isForCutting() ? 'Cutting — ' . ($this->job?->customer?->name ?? '') : ($this->purchaseOrder?->vendor?->name ?? '');
    }

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

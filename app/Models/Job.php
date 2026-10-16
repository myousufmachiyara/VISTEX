<?php
// app/Models/Job.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Job extends Model
{
    use SoftDeletes;

    protected $table = 'job_orders';

    protected $fillable = [
        'job_no', 'customer_id', 'buyer_name', 'consignee_name', 'shipping_address',
        'customer_po_number', 'customer_reference',
        'order_date', 'expected_date', 'currency', 'exchange_rate',
        'payment_term_type', 'payment_term_days', 'payment_term_note', 'dispatch_tolerance_pct',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
        'remarks', 'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'expected_date' => 'date',
        'approved_at'   => 'datetime',
        'attachments'   => 'array',
        'exchange_rate' => 'decimal:4',
    ];

    public const CURRENCIES = ['PKR', 'USD', 'EUR', 'GBP', 'AED'];

    public function customer()  { return $this->belongsTo(Customer::class, 'customer_id'); }
    public function approver()  { return $this->belongsTo(User::class, 'approved_by'); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function items()     { return $this->hasMany(JobItem::class, 'job_id'); }
    public function terms()     { return $this->hasMany(JobOrderTerm::class, 'job_id')->orderBy('id'); }

    public function getTotalMetersAttribute(): float
    {
        return round((float) $this->items->sum('quantity'), 3);
    }

    // Order value in rupees (same as total when the order is in PKR)
    public function getTotalPkrAttribute(): float
    {
        return round((float) $this->total_amount * ($this->currency === 'PKR' ? 1 : (float) $this->exchange_rate), 2);
    }

    public function isEditable(): bool { return in_array($this->status, ['Pending', 'Rejected'], true); }

    public function scopeApproved($q) { return $q->where('status', 'Approved'); }

    public function canBeApprovedBy(User $user): bool
    {
        return $user->hasRole('superadmin');
    }
}
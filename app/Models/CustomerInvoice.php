<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerInvoice extends Model
{
    public const STATUSES = ['Unpaid' => 'Unpaid', 'PartiallyPaid' => 'Partially Paid', 'Paid' => 'Paid', 'Cancelled' => 'Cancelled'];

    protected $fillable = ['invoice_no', 'customer_id', 'invoice_date', 'due_date', 'currency', 'exchange_rate', 'subtotal', 'tax_amount',
        'total', 'total_pkr', 'paid_amount', 'status', 'remarks', 'created_by'];
    protected $casts = ['invoice_date' => 'date', 'due_date' => 'date', 'exchange_rate' => 'float', 'subtotal' => 'float',
        'tax_amount' => 'float', 'total' => 'float', 'total_pkr' => 'float', 'paid_amount' => 'float'];

    public function items()       { return $this->hasMany(CustomerInvoiceItem::class); }
    public function customer()    { return $this->belongsTo(Customer::class); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by'); }
    public function allocations() { return $this->hasMany(CustomerReceiptAllocation::class); }

    public function getBalanceAttribute(): float { return round($this->total - $this->paid_amount, 2); }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? $this->status; }
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) { 'Paid' => 'success', 'PartiallyPaid' => 'primary', 'Cancelled' => 'secondary', default => 'warning text-dark' };
    }
    public function isOverdue(): bool { return $this->balance > 0.004 && $this->due_date && $this->due_date->isPast() && $this->status !== 'Cancelled'; }
}

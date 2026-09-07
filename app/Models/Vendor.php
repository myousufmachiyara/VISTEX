<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $table = 'vendors';

    public const TYPES = [
        'spinning_mill'   => 'Spinning Mill',
        'weaving_mill'    => 'Weaving Mill',
        'processing_mill' => 'Processing Mill',
        'packager'        => 'Packager',
        'courier'         => 'Courier',
        'other'           => 'Other',
    ];

    protected $fillable = [
        'name', 'vendor_type', 'phone', 'email', 'contact_person', 'address', 'city',
        'tax_id_number', 'payment_terms_type', 'payment_days', 'currency',
        'opening_balance', 'opening_type', 'opening_balance_date',
        'notes', 'is_active', 'created_by', 'updated_by', 'ntn_number',

    ];

    protected $casts = [
        'opening_balance'      => 'decimal:2',
        'opening_balance_date' => 'date',
        'payment_days'         => 'integer',
        'is_active'            => 'boolean',
    ];

    public function getOpeningBalanceSignedAttribute(): float
    {
        $amt = (float) $this->opening_balance;
        return $this->opening_type === 'payable' ? -abs($amt) : abs($amt);
    }

    public function voucherEntries()
    {
        return $this->hasMany(VoucherEntry::class, 'party_id')->where('party_type', 'vendor');
    }

    public function getBalanceAttribute(): float
    {
        $net = VoucherEntry::where('party_type', 'vendor')
            ->where('party_id', $this->id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
            ->value('net');

        return $this->opening_balance_signed + (float) $net;
    }

    public function calculateDueDate(\DateTimeInterface $invoiceDate): \Carbon\Carbon
    {
        $date = \Carbon\Carbon::parse($invoiceDate);

        return match ($this->payment_terms_type) {
            'days_after_invoice' => $date->copy()->addDays($this->payment_days),
            'of_current_month'   => $date->copy()->day(min($this->payment_days, $date->daysInMonth)),
            'of_following_month' => $date->copy()->addMonthNoOverflow()->day(min($this->payment_days, $date->copy()->addMonthNoOverflow()->daysInMonth)),
            default               => $date->copy()->addDays(30),
        };
    }

    public function isGstRegistered(): bool
    {
        return !empty($this->tax_id_number);
    }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function toLookup(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'vendor_type' => $this->vendor_type, 'balance' => $this->balance];
    }
}
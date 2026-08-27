<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccounts extends Model
{
    use SoftDeletes;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'account_code', 'shoa_id', 'name', 'account_type',
        'receivables', 'payables', 'credit_limit',
        'opening_balance', 'opening_date',
        'remarks', 'address', 'contact_no',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'receivables'      => 'decimal:2',
        'payables'         => 'decimal:2',
        'credit_limit'     => 'decimal:2',
        'opening_balance'  => 'decimal:2',
        'opening_date'     => 'date',
        'is_active'        => 'boolean',
    ];

    public function subHead() { return $this->belongsTo(SubHeadOfAccounts::class, 'shoa_id'); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    // Running balance = opening + all posted voucher entries
    public function getBalanceAttribute(): float
    {
        $net = VoucherEntry::where('account_id', $this->id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
            ->value('net');

        return (float) $this->opening_balance + (float) $net;
    }
}
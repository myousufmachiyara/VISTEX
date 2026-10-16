<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReceipt extends Model
{
    public const METHODS = ['cash' => 'Cash', 'bank' => 'Bank Transfer', 'cheque' => 'Cheque'];

    protected $fillable = ['receipt_no', 'customer_id', 'receipt_date', 'account_id', 'method', 'reference', 'currency', 'exchange_rate',
        'amount', 'amount_pkr', 'unallocated', 'exchange_difference', 'remarks', 'created_by'];
    protected $casts = ['receipt_date' => 'date', 'exchange_rate' => 'float', 'amount' => 'float', 'amount_pkr' => 'float',
        'unallocated' => 'float', 'exchange_difference' => 'float'];

    public function customer()    { return $this->belongsTo(Customer::class); }
    public function account()     { return $this->belongsTo(ChartOfAccounts::class, 'account_id'); }
    public function allocations() { return $this->hasMany(CustomerReceiptAllocation::class); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pdc extends Model
{
    use SoftDeletes;

    protected $table = 'pdcs';

    protected $fillable = [
        'pdc_no', 'party_type', 'party_id', 'reference_type', 'reference_id',
        'amount', 'due_date', 'status',
        'bank_account_id', 'cheque_no', 'unsigned_cheque_image',
        'signed_cheque_image',
        'issue_method', 'receiver_name', 'receiver_contact', 'receiver_cnic',
        'receipt_signed_image', 'bank_slip_image', 'issued_date',
        'cleared_date', 'bounced_date', 'bounced_reason',
        'remarks', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'due_date'      => 'date',
        'issued_date'   => 'date',
        'cleared_date'  => 'date',
        'bounced_date'  => 'date',
    ];

    public function bankAccount() { return $this->belongsTo(ChartOfAccounts::class, 'bank_account_id'); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by'); }

    public function party()
    {
        return $this->party_type === 'customer'
            ? $this->belongsTo(Customer::class, 'party_id')
            : $this->belongsTo(Vendor::class, 'party_id');
    }

    public function scopePending($q)  { return $q->where('status', 'Pending'); }
    public function scopeUncleared($q) { return $q->whereIn('status', ['Issued']); } // "unclear cheques" list
}
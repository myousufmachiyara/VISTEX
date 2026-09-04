<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PdcCheque extends Model
{
    use SoftDeletes;
    protected $table = 'pdc_cheques';

    protected $fillable = [
        'pdc_id', 'sequence_no', 'amount', 'status',
        'bank_account_id', 'cheque_no', 'unsigned_cheque_image', 'signed_cheque_image',
        'issue_method', 'receiver_name', 'receiver_contact', 'receiver_cnic',
        'receipt_signed_image', 'bank_slip_image', 'issued_date',
        'cleared_date', 'bounced_date', 'bounced_reason', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2', 'issued_date' => 'date', 'cleared_date' => 'date', 'bounced_date' => 'date',
    ];

    public function pdc()         { return $this->belongsTo(Pdc::class, 'pdc_id'); }
    public function bankAccount() { return $this->belongsTo(ChartOfAccounts::class, 'bank_account_id'); }

    public function scopeUncleared($q) { return $q->where('status', 'Issued'); }
}
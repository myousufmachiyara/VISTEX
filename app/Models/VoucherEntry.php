<?php
// app/Models/VoucherEntry.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherEntry extends Model
{
    protected $table = 'voucher_entries';

    protected $fillable = ['voucher_id', 'account_id', 'debit', 'credit', 'party_type', 'party_id', 'narration'];

    protected $casts = ['debit' => 'decimal:2', 'credit' => 'decimal:2'];

    public function voucher() { return $this->belongsTo(Voucher::class, 'voucher_id'); }
    public function account() { return $this->belongsTo(ChartOfAccounts::class, 'account_id'); }

    public function party()
    {
        return $this->party_type === 'customer'
            ? $this->belongsTo(Customer::class, 'party_id')
            : $this->belongsTo(Vendor::class, 'party_id');
    }
}
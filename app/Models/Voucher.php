<?php
// app/Models/Voucher.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use SoftDeletes;

    protected $table = 'vouchers';

    protected $fillable = [
        'voucher_no', 'type', 'voucher_date', 'narration',
        'reference_type', 'reference_id', 'created_by', 'updated_by',
    ];

    protected $casts = ['voucher_date' => 'date'];

    public function entries() { return $this->hasMany(VoucherEntry::class, 'voucher_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function getTotalDebitAttribute(): float
    {
        return (float) $this->entries->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->entries->sum('credit');
    }
}
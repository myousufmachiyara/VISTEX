<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pdc extends Model
{
    use SoftDeletes;
    protected $table = 'pdcs';

    protected $fillable = ['pdc_no', 'party_type', 'party_id', 'reference_type', 'reference_id', 'amount', 'due_date', 'remarks', 'created_by', 'updated_by'];
    protected $casts = ['amount' => 'decimal:2', 'due_date' => 'date'];

    public function cheques() { return $this->hasMany(PdcCheque::class, 'pdc_id')->orderBy('sequence_no'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function party()
    {
        return $this->party_type === 'customer'
            ? $this->belongsTo(Customer::class, 'party_id')
            : ($this->party_type === 'broker' ? $this->belongsTo(Broker::class, 'party_id') : $this->belongsTo(Vendor::class, 'party_id'));
    }

    public function getAllocatedAmountAttribute(): float
    {
        return (float) $this->cheques()->sum('amount');
    }

    public function getPendingAmountAttribute(): float
    {
        return round((float) $this->amount - $this->allocated_amount, 2);
    }

    public function getOverallStatusAttribute(): string
    {
        if ($this->cheques()->count() === 0) return 'Pending';
        if ($this->pending_amount > 0.001) return 'PartiallyAllocated';
        if ($this->cheques()->where('status', '!=', 'Cleared')->exists()) return 'Allocated';
        return 'Cleared';
    }
}
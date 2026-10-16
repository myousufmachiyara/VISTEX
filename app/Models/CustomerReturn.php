<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReturn extends Model
{
    protected $fillable = ['return_no', 'customer_id', 'location_id', 'return_date', 'reason', 'remarks', 'cost_amount', 'created_by'];
    protected $casts = ['return_date' => 'date', 'cost_amount' => 'float'];

    public function items()       { return $this->hasMany(CustomerReturnItem::class); }
    public function customer()    { return $this->belongsTo(Customer::class); }
    public function location()    { return $this->belongsTo(Location::class); }
    public function creditNotes() { return $this->hasMany(CustomerCreditNote::class); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by'); }
}

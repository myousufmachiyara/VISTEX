<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCreditNote extends Model
{
    protected $fillable = ['credit_no', 'customer_id', 'customer_invoice_id', 'customer_return_id', 'credit_date', 'currency', 'exchange_rate',
        'subtotal', 'tax_amount', 'total', 'total_pkr', 'unapplied', 'reason', 'created_by'];
    protected $casts = ['credit_date' => 'date', 'exchange_rate' => 'float', 'subtotal' => 'float', 'tax_amount' => 'float',
        'total' => 'float', 'total_pkr' => 'float', 'unapplied' => 'float'];

    public function customer()     { return $this->belongsTo(Customer::class); }
    public function invoice()      { return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id'); }
    public function return()       { return $this->belongsTo(CustomerReturn::class, 'customer_return_id'); }
    public function applications() { return $this->hasMany(CustomerCreditApplication::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCreditApplication extends Model
{
    protected $fillable = ['customer_credit_note_id', 'customer_invoice_id', 'amount', 'exchange_difference'];
    protected $casts = ['amount' => 'float', 'exchange_difference' => 'float'];

    public function creditNote() { return $this->belongsTo(CustomerCreditNote::class, 'customer_credit_note_id'); }
    public function invoice()    { return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id'); }
}

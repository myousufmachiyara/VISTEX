<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReceiptAllocation extends Model
{
    protected $fillable = ['customer_receipt_id', 'customer_invoice_id', 'amount', 'exchange_difference', 'applied_later'];
    protected $casts = ['amount' => 'float', 'exchange_difference' => 'float', 'applied_later' => 'boolean'];

    public function receipt() { return $this->belongsTo(CustomerReceipt::class, 'customer_receipt_id'); }
    public function invoice() { return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id'); }
}

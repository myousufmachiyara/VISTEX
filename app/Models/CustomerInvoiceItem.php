<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerInvoiceItem extends Model
{
    protected $fillable = ['customer_invoice_id', 'stock_movement_item_id', 'product_id', 'sale_order_item_id', 'job_item_id', 'description',
        'delivered_qty', 'quantity', 'unit', 'rate', 'discount_pct', 'amount', 'tax_rate', 'tax_amount', 'credited_qty'];
    protected $casts = ['delivered_qty' => 'float', 'quantity' => 'float', 'rate' => 'float', 'discount_pct' => 'float',
        'amount' => 'float', 'tax_rate' => 'float', 'tax_amount' => 'float', 'credited_qty' => 'float'];

    public function invoice()           { return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id'); }
    public function product()           { return $this->belongsTo(Product::class); }
    public function stockMovementItem() { return $this->belongsTo(StockMovementItem::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YarnInProcessLedger extends Model
{
    protected $table = 'yarn_in_process_ledger';
    public $timestamps = true;

    protected $fillable = ['purchase_order_id', 'vendor_id', 'product_id', 'quantity', 'amount', 'reference_type', 'reference_id', 'entry_date'];
    protected $casts = ['quantity' => 'decimal:3', 'amount' => 'decimal:2', 'entry_date' => 'date'];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function vendor()        { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function product()       { return $this->belongsTo(Product::class, 'product_id'); }

    // Returns current balance (qty + amount) of a given yarn product
    // still "in process" at the mill for a specific weaving-type PO.
    public static function balanceForCpoProduct(int $purchaseOrderId, int $productId): array
    {
        $row = self::where('purchase_order_id', $purchaseOrderId)
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity),0) as qty, COALESCE(SUM(amount),0) as amt')
            ->first();

        return ['quantity' => (float) $row->qty, 'amount' => (float) $row->amt];
    }
}
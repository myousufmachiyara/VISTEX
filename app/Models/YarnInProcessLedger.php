<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YarnInProcessLedger extends Model
{
    protected $table = 'yarn_in_process_ledger';
    public $timestamps = true;

    protected $fillable = ['cpo_id', 'vendor_id', 'product_id', 'quantity', 'amount', 'reference_type', 'reference_id', 'entry_date'];
    protected $casts = ['quantity' => 'decimal:3', 'amount' => 'decimal:2', 'entry_date' => 'date'];

    public function cpo()     { return $this->belongsTo(ConversionPurchaseOrder::class, 'cpo_id'); }
    public function vendor()  { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function product() { return $this->belongsTo(Product::class, 'product_id'); }

    public static function balanceForCpoProduct(int $cpoId, int $productId): array
    {
        $row = self::where('cpo_id', $cpoId)->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity),0) as qty, COALESCE(SUM(amount),0) as amt')->first();
        return ['quantity' => (float) $row->qty, 'amount' => (float) $row->amt];
    }

    // "Exactly what qty of yarn each weaving mill currently holds" — per vendor, per product
    public static function balancesByVendor()
    {
        return self::selectRaw('vendor_id, product_id, SUM(quantity) as qty, SUM(amount) as amt')
            ->groupBy('vendor_id', 'product_id')
            ->having('qty', '>', 0.001)
            ->with('vendor', 'product')
            ->get();
    }
}
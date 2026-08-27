<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationStockLedger extends Model
{
    protected $table = 'location_stock_ledger';
    public $timestamps = true;

    protected $fillable = ['doc_no', 'location_id', 'product_id', 'status', 'quantity', 'amount', 'reference_type', 'reference_id', 'entry_date', 'remarks'];
    protected $casts = ['quantity' => 'decimal:3', 'amount' => 'decimal:2', 'entry_date' => 'date'];

    public function location() { return $this->belongsTo(Location::class, 'location_id'); }
    public function product()  { return $this->belongsTo(Product::class, 'product_id'); }

    public static function balance(int $locationId, int $productId, string $status = 'fresh'): float
    {
        return (float) self::where('location_id', $locationId)
            ->where('product_id', $productId)
            ->where('status', $status)
            ->sum('quantity');
    }
}
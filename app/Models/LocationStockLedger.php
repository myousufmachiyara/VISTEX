<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationStockLedger extends Model
{
    protected $table = 'location_stock_ledger';
    public $timestamps = true;

    protected $fillable = ['doc_no', 'location_id', 'product_id', 'status', 'lot_no', 'quantity', 'amount', 'reference_type', 'reference_id', 'entry_date', 'remarks'];
    protected $casts = ['quantity' => 'decimal:3', 'amount' => 'decimal:2', 'entry_date' => 'date'];

    public function location() { return $this->belongsTo(Location::class, 'location_id'); }
    public function product()  { return $this->belongsTo(Product::class, 'product_id'); }

    public static function balance(int $locationId, int $productId, string $status = 'fresh', ?string $lotNo = null): float
    {
        $query = self::where('location_id', $locationId)->where('product_id', $productId)->where('status', $status);
        if ($lotNo !== null) $query->where('lot_no', $lotNo);
        return (float) $query->sum('quantity');
    }

    public static function availableLots(int $locationId, int $productId): \Illuminate\Support\Collection
    {
        return self::where('location_id', $locationId)->where('product_id', $productId)
            ->where('status', 'fresh')->whereNotNull('lot_no')
            ->groupBy('lot_no')->selectRaw('lot_no, SUM(quantity) as qty')
            ->having('qty', '>', 0.001)->pluck('qty', 'lot_no');
    }
}
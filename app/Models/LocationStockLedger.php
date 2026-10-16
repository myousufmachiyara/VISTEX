<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationStockLedger extends Model
{
    protected $table = 'location_stock_ledger';
    public $timestamps = true;

    protected $fillable = ['doc_no', 'location_id', 'product_id', 'status', 'lot_no', 'grade', 'quantity', 'amount', 'reference_type', 'reference_id', 'entry_date', 'remarks'];
    protected $casts = ['quantity' => 'decimal:3', 'amount' => 'decimal:2', 'entry_date' => 'date'];

    public function location() { return $this->belongsTo(Location::class, 'location_id'); }
    public function product()  { return $this->belongsTo(Product::class, 'product_id'); }

    public static function balance(int $locationId, int $productId, string $status = 'fresh', ?string $lotNo = null, ?string $grade = null): float
    {
        $query = self::where('location_id', $locationId)->where('product_id', $productId)->where('status', $status);
        if ($lotNo !== null) $query->where('lot_no', $lotNo);
        if ($grade) $query->where('grade', $grade);
        return (float) $query->sum('quantity');
    }

    public static function availableLots(int $locationId, int $productId): \Illuminate\Support\Collection
    {
        return self::where('location_id', $locationId)->where('product_id', $productId)
            ->where('status', 'fresh')->whereNotNull('lot_no')
            ->groupBy('lot_no')->selectRaw('lot_no, SUM(quantity) as qty')
            ->having('qty', '>', 0.001)->pluck('qty', 'lot_no');
    }

    /**
     * Which lots to take $qty from at a location, oldest first (unlotted stock last).
     * With $lotNo, takes only from that lot. Returns [['lot_no' => ?string, 'quantity' => float], ...].
     */
    public static function allocate(int $locationId, int $productId, float $qty, ?string $lotNo = null, string $status = 'fresh', ?string $grade = null): array
    {
        $buckets = self::where('location_id', $locationId)->where('product_id', $productId)->where('status', $status)
            ->when($lotNo !== null && $lotNo !== '', fn($q) => $q->where('lot_no', $lotNo))
            ->when($grade, fn($q) => $q->where('grade', $grade))
            ->groupBy('lot_no')->selectRaw('lot_no, SUM(quantity) AS qty, MIN(entry_date) AS first_date')
            ->havingRaw('SUM(quantity) > 0.0005')->get()
            ->sortBy(fn($b) => [$b->lot_no === null ? 1 : 0, $b->first_date, $b->lot_no])->values();

        $out = []; $left = round($qty, 3);
        foreach ($buckets as $b) {
            if ($left <= 0.0005) break;
            $take = round(min($left, (float) $b->qty), 3);
            $out[] = ['lot_no' => $b->lot_no, 'quantity' => $take];
            $left = round($left - $take, 3);
        }
        if ($left > 0.0005) {
            $have = round($qty - $left, 3);
            throw new \Exception("Not enough stock" . ($lotNo ? " in lot {$lotNo}" : '') . " — available {$have}, needed {$qty}.");
        }
        return $out;
    }

    // Cost per unit of one lot at a location (a lot is one PO's price); falls back to the location average
    public static function lotRate(int $locationId, int $productId, ?string $lotNo, float $fallback, string $status = 'fresh', ?string $grade = null): float
    {
        if ($lotNo === null || $lotNo === '') return $fallback;
        $r = self::where('location_id', $locationId)->where('product_id', $productId)->where('status', $status)
            ->where('lot_no', $lotNo)->when($grade, fn($q) => $q->where('grade', $grade))->selectRaw('SUM(quantity) AS q, SUM(amount) AS a')->first();
        return ($r && (float) $r->q > 0.0005) ? (float) $r->a / (float) $r->q : $fallback;
    }
}

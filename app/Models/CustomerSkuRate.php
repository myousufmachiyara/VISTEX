<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSkuRate extends Model
{
    protected $table = 'customer_sku_rates';

    protected $fillable = ['customer_id', 'product_id', 'rate', 'effective_date', 'remarks', 'created_by'];

    protected $casts = [
        'rate'           => 'decimal:2',
        'effective_date' => 'date',
    ];

    public function customer() { return $this->belongsTo(Customer::class, 'customer_id'); }
    public function product()  { return $this->belongsTo(Product::class, 'product_id'); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }

    // Latest rate as of a given date (defaults to today) — the current price to charge
    public static function currentRate(int $customerId, int $productId, ?string $asOfDate = null): ?float
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $row = self::where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->where('effective_date', '<=', $asOfDate)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        return $row ? (float) $row->rate : null;
    }

    // Full history, most recent first
    public static function historyFor(int $customerId, int $productId)
    {
        return self::where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->with('creator')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();
    }
}
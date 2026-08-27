<?php

namespace App\Services;

use App\Models\CustomerSkuRate;
use Illuminate\Support\Facades\DB;

class CustomerSkuRateService
{
    // Adds a NEW rate entry — never overwrites, preserves history
    public function addRate(array $data, ?int $userId = null): CustomerSkuRate
    {
        return DB::transaction(function () use ($data, $userId) {
            return CustomerSkuRate::create([
                'customer_id'     => $data['customer_id'],
                'product_id'      => $data['product_id'],
                'rate'            => $data['rate'],
                'effective_date'  => $data['effective_date'],
                'remarks'         => $data['remarks'] ?? null,
                'created_by'      => $userId,
            ]);
        });
    }

    public function delete(CustomerSkuRate $rate): void
    {
        $rate->delete();
    }

    // Full rate history for one customer+SKU pair, newest first
    public function historyFor(int $customerId, int $productId)
    {
        return CustomerSkuRate::with('customer', 'product')
            ->where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();
    }
}
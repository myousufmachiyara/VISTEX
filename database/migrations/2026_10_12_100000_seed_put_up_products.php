<?php

use App\Support\PutUps;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vistex put-ups (meter consumption per piece) as products in the SKU category, unit Pieces.
 * List lives in App\Support\PutUps. Safe to re-run: existing SKU codes are skipped.
 * On a brand-new database the categories don't exist yet; DatabaseSeeder adds the put-ups then.
 */
return new class extends Migration
{
    public function up(): void
    {
        PutUps::seed();
    }

    // Removes only these SKU codes, and only those with no stock movements.
    public function down(): void
    {
        $skus = array_column(PutUps::all(), 0);
        $used = DB::table('location_stock_ledger')->join('products', 'products.id', '=', 'location_stock_ledger.product_id')
            ->whereIn('products.sku', $skus)->pluck('products.sku')->unique()->all();
        DB::table('products')->whereIn('sku', array_diff($skus, $used))->delete();
    }
};

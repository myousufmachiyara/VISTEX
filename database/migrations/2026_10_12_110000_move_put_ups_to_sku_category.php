<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The put-ups (SKU codes PU-…) are SKUs. Moves any that were added under Cut Pcs
 * into the SKU category. Does nothing if they are already there.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sku = DB::table('product_categories')->where('code', 'sku')->value('id');
        $cut = DB::table('product_categories')->where('code', 'cut_pcs')->value('id');
        if (!$sku || !$cut) return;

        DB::table('products')->where('sku', 'like', 'PU-%')->where('category_id', $cut)
            ->update(['category_id' => $sku, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Intentionally left as SKU.
    }
};

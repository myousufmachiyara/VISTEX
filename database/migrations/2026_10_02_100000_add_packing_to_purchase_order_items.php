<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Purchase PO lines can be entered as packs, e.g. 20 bags x 10 lbs.
 *   pack_qty      = number of packs/bags (20)
 *   qty_per_pack  = quantity of the item's unit in one pack (10 lbs)
 *   quantity      = pack_qty x qty_per_pack (200 lbs) — unchanged meaning, so
 *                   receiving, outstanding and stock keep working in the item's unit.
 * Existing lines become "quantity x 1".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('purchase_order_items', 'pack_qty')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->decimal('pack_qty', 15, 3)->nullable()->after('measurement_unit');
                $table->decimal('qty_per_pack', 15, 4)->nullable()->after('pack_qty');
            });
        }

        DB::table('purchase_order_items')
            ->whereNull('pack_qty')
            ->whereNotNull('product_id')          // purchase lines; processing lines use pattern codes
            ->update(['pack_qty' => DB::raw('quantity'), 'qty_per_pack' => 1]);
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['pack_qty', 'qty_per_pack']);
        });
    }
};

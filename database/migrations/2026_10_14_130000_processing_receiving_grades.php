<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Processing PO receiving: finished fabric counted as A / B / C quality.
 *  - challan_items:            gate counts per grade
 *  - purchase_receiving_items: accepted meters per grade
 *  - purchase_receivings:      greige used + processing charge, for costing
 *  - location_stock_ledger:    grade of finished fabric stock (null for everything else)
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['challan_items', 'purchase_receiving_items'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['qty_a', 'qty_b', 'qty_c'] as $c) {
                    if (!Schema::hasColumn($table, $c)) $t->decimal($c, 15, 3)->nullable();
                }
            });
        }

        Schema::table('purchase_receivings', function (Blueprint $t) {
            if (!Schema::hasColumn('purchase_receivings', 'greige_consumed_meta'))     $t->json('greige_consumed_meta')->nullable();
            if (!Schema::hasColumn('purchase_receivings', 'greige_cost_amount'))       $t->decimal('greige_cost_amount', 15, 2)->default(0);
            if (!Schema::hasColumn('purchase_receivings', 'processing_charge_amount')) $t->decimal('processing_charge_amount', 15, 2)->default(0);
        });

        Schema::table('location_stock_ledger', function (Blueprint $t) {
            if (!Schema::hasColumn('location_stock_ledger', 'grade')) {
                $t->string('grade', 1)->nullable()->after('lot_no');
                $t->index('grade', 'idx_lsl_grade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('location_stock_ledger', function (Blueprint $t) {
            if (Schema::hasColumn('location_stock_ledger', 'grade')) { $t->dropIndex('idx_lsl_grade'); $t->dropColumn('grade'); }
        });
        Schema::table('purchase_receivings', function (Blueprint $t) {
            foreach (['greige_consumed_meta', 'greige_cost_amount', 'processing_charge_amount'] as $c) {
                if (Schema::hasColumn('purchase_receivings', $c)) $t->dropColumn($c);
            }
        });
        foreach (['challan_items', 'purchase_receiving_items'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['qty_a', 'qty_b', 'qty_c'] as $c) if (Schema::hasColumn($table, $c)) $t->dropColumn($c);
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reprocessing finished fabric at a mill.
 *  - A reprocess order is a Processing PO with reprocess_of_po_id set. It keeps the fabric's lot #
 *    (reprocess_lot_no) and is either FOC (no charge, no approval) or charged (normal PO approval).
 *  - Stock movement lines can now take stock from the QC "reprocess" hold or from one grade of fresh stock.
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('purchase_orders', 'reprocess_of_po_id')) $t->unsignedBigInteger('reprocess_of_po_id')->nullable()->index();
            if (!Schema::hasColumn('purchase_orders', 'reprocess_lot_no'))   $t->string('reprocess_lot_no', 100)->nullable();
            if (!Schema::hasColumn('purchase_orders', 'is_foc'))             $t->boolean('is_foc')->default(false);
        });
        Schema::table('stock_movement_items', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_movement_items', 'source_status')) $t->string('source_status', 20)->default('fresh');
            if (!Schema::hasColumn('stock_movement_items', 'source_grade'))  $t->string('source_grade', 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movement_items', function (Blueprint $t) {
            foreach (['source_status', 'source_grade'] as $c) if (Schema::hasColumn('stock_movement_items', $c)) $t->dropColumn($c);
        });
        Schema::table('purchase_orders', function (Blueprint $t) {
            foreach (['reprocess_of_po_id', 'reprocess_lot_no', 'is_foc'] as $c) if (Schema::hasColumn('purchase_orders', $c)) $t->dropColumn($c);
        });
    }
};

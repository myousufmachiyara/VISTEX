<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the genuinely missing column
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'warp_conversion_pct')) {
                $table->decimal('warp_conversion_pct', 10, 6)->nullable()->after('sizing_lbs');
            }
        });

        // 2. Drop dead/orphan columns no longer referenced anywhere in the model or service
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach ([
                'reed_input',
                'reed_space_input',
                'warp_shrinkage_pct_old',
                'weft_shrinkage_pct',
                'broker_commission_type',
                'broker_commission_value',
                'total_greige_qty_required',
            ] as $col) {
                if (Schema::hasColumn('purchase_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'warp_conversion_pct')) {
                $table->dropColumn('warp_conversion_pct');
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('reed_input', 10, 4)->nullable();
            $table->decimal('reed_space_input', 10, 4)->nullable();
            $table->decimal('warp_shrinkage_pct_old', 10, 6)->nullable();
            $table->decimal('weft_shrinkage_pct', 10, 6)->nullable();
            $table->string('broker_commission_type', 10)->nullable();
            $table->decimal('broker_commission_value', 15, 4)->default(0);
            $table->decimal('total_greige_qty_required', 15, 6)->nullable();
        });
    }
};
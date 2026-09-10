<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'total_yarn_cost_per_meter')) {
                $table->decimal('total_yarn_cost_per_meter', 15, 2)->nullable()->after('weft_yarn_rate');
            }
            if (!Schema::hasColumn('purchase_orders', 'fabric_cost')) {
                $table->decimal('fabric_cost', 15, 2)->nullable()->after('weaving_per_meter');
            }
        });

        // total_yarn_weight_consumed was originally decimal(15,3) but the
        // service now rounds this value to 4 decimal places — widen the
        // column so precision isn't silently truncated.
        if (Schema::hasColumn('purchase_orders', 'total_yarn_weight_consumed')) {
            DB::statement('ALTER TABLE purchase_orders MODIFY total_yarn_weight_consumed DECIMAL(15,4) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'total_yarn_cost_per_meter')) {
                $table->dropColumn('total_yarn_cost_per_meter');
            }
            if (Schema::hasColumn('purchase_orders', 'fabric_cost')) {
                $table->dropColumn('fabric_cost');
            }
        });

        if (Schema::hasColumn('purchase_orders', 'total_yarn_weight_consumed')) {
            DB::statement('ALTER TABLE purchase_orders MODIFY total_yarn_weight_consumed DECIMAL(15,3) NULL');
        }
    }
};
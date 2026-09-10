<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename existing columns to match the Model/blade/service naming
        //    (requires composer require doctrine/dbal if not already installed)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('weft_shrinkage_pct', 'weft_conversion_pct');
            $table->renameColumn('weaving_rate', 'weaving_cost_per_meter');
            $table->renameColumn('sizing_per_meter', 'sizing_rate_per_meter');
        });

        // 2. Drop columns nothing ever writes to (never submitted by the form / unused)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['warp_shrinkage_pct', 'rate_per_meter']);
        });

        // 3. Add columns the blade submits / the service returns that never existed
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('reed', 10, 4)->nullable()->after('greige_product_id');
            $table->decimal('reed_space', 12, 4)->nullable()->after('reed_count');
            $table->decimal('warping', 10, 4)->nullable()->default(1)->after('sizing_lbs');

            $table->decimal('warp_yarn_cost_price', 15, 4)->nullable()->after('weft_conversion_pct');
            $table->decimal('weft_yarn_cost_price', 15, 4)->nullable()->after('warp_yarn_cost_price');
            $table->decimal('warp_yarn_rate', 15, 2)->nullable()->after('weft_yarn_cost_price');
            $table->decimal('weft_yarn_rate', 15, 2)->nullable()->after('warp_yarn_rate');

            $table->decimal('warp_gsm', 10, 3)->nullable()->after('gsm');
            $table->decimal('weft_gsm', 10, 3)->nullable()->after('warp_gsm');
            $table->decimal('gsm_kg', 10, 4)->nullable()->after('weft_gsm');

            $table->decimal('weaving_per_meter', 15, 4)->nullable()->after('sizing_rate_per_meter');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'reed', 'reed_space', 'warping',
                'warp_yarn_cost_price', 'weft_yarn_cost_price',
                'warp_yarn_rate', 'weft_yarn_rate',
                'warp_gsm', 'weft_gsm', 'gsm_kg',
                'weaving_per_meter',
            ]);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('warp_shrinkage_pct', 10, 6)->nullable();
            $table->decimal('rate_per_meter', 15, 4)->nullable();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('weft_conversion_pct', 'weft_shrinkage_pct');
            $table->renameColumn('weaving_cost_per_meter', 'weaving_rate');
            $table->renameColumn('sizing_rate_per_meter', 'sizing_per_meter');
        });
    }
};
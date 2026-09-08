<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('reed', 10, 4)->nullable()->after('reed_count');
            $table->decimal('warping', 10, 4)->nullable()->after('sizing_lbs');
            $table->decimal('warp_yarn_cost_price', 15, 4)->nullable()->after('warping');
            $table->decimal('weft_yarn_cost_price', 15, 4)->nullable()->after('warp_yarn_cost_price');

            $table->renameColumn('warp_conversion_pct', 'warp_shrinkage_pct_old'); // temp, see note below
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['reed', 'warping', 'warp_yarn_cost_price', 'weft_yarn_cost_price']);
        });
    }
};
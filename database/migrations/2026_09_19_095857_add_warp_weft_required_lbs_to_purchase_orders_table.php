<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'warp_required_lbs')) {
                $table->decimal('warp_required_lbs', 15, 4)->nullable()->after('total_yarn_weight_consumed');
            }
            if (!Schema::hasColumn('purchase_orders', 'weft_required_lbs')) {
                $table->decimal('weft_required_lbs', 15, 4)->nullable()->after('warp_required_lbs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'warp_required_lbs')) $table->dropColumn('warp_required_lbs');
            if (Schema::hasColumn('purchase_orders', 'weft_required_lbs')) $table->dropColumn('weft_required_lbs');
        });
    }
};
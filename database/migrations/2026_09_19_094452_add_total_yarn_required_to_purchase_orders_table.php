<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'total_yarn_required')) {
                $table->decimal('total_yarn_required', 15, 4)->nullable()->after('total_yarn_weight_consumed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'total_yarn_required')) {
                $table->dropColumn('total_yarn_required');
            }
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('weft_conversion_pct', 10, 6)->nullable()->after('warp_conversion_pct');
            $table->decimal('reed_space_input', 10, 4)->nullable()->after('reed_count');
            $table->decimal('reed_input', 10, 4)->nullable()->after('reed_space_input');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['weft_conversion_pct', 'reed_space_input','reed_input']);
        });
    }
};
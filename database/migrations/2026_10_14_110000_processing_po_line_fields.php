<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Processing PO lines carry design and colour scheme from the customer order. Safe to re-run.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $t) {
            if (!Schema::hasColumn('purchase_order_items', 'design'))       $t->string('design', 191)->nullable()->after('pattern_code');
            if (!Schema::hasColumn('purchase_order_items', 'color_scheme')) $t->string('color_scheme', 191)->nullable()->after('design');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $t) {
            foreach (['design', 'color_scheme'] as $c) if (Schema::hasColumn('purchase_order_items', $c)) $t->dropColumn($c);
        });
    }
};

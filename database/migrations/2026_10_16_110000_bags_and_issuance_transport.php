<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - challan_items.packs: number of bags / packs counted at the gate per line
 * - issuances: transport details (as on challans), number of bags (record only), prepared by / received by names
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challan_items', function (Blueprint $t) {
            if (!Schema::hasColumn('challan_items', 'packs')) $t->decimal('packs', 12, 2)->nullable();
        });
        Schema::table('issuances', function (Blueprint $t) {
            if (!Schema::hasColumn('issuances', 'vehicle_no'))       $t->string('vehicle_no', 30)->nullable();
            if (!Schema::hasColumn('issuances', 'driver_name'))      $t->string('driver_name', 100)->nullable();
            if (!Schema::hasColumn('issuances', 'driver_contact'))   $t->string('driver_contact', 30)->nullable();
            if (!Schema::hasColumn('issuances', 'no_of_bags'))       $t->decimal('no_of_bags', 12, 2)->nullable();
            if (!Schema::hasColumn('issuances', 'prepared_by_name')) $t->string('prepared_by_name', 100)->nullable();
            if (!Schema::hasColumn('issuances', 'received_by_name')) $t->string('received_by_name', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('challan_items', function (Blueprint $t) {
            if (Schema::hasColumn('challan_items', 'packs')) $t->dropColumn('packs');
        });
        Schema::table('issuances', function (Blueprint $t) {
            foreach (['vehicle_no', 'driver_name', 'driver_contact', 'no_of_bags', 'prepared_by_name', 'received_by_name'] as $c) {
                if (Schema::hasColumn('issuances', $c)) $t->dropColumn($c);
            }
        });
    }
};

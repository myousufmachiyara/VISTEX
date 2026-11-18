<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issuance → "Processed Fabric for Cutting" (against a customer job).
 * Each line remembers the job line it is for and the QC grade of the fabric issued.
 * Issued fabric stays in the warehouse with stock status "for_cutting" until it is cut.
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issuance_items', function (Blueprint $t) {
            if (!Schema::hasColumn('issuance_items', 'job_item_id')) {
                $t->unsignedBigInteger('job_item_id')->nullable()->after('purchase_order_item_id');
                $t->index('job_item_id', 'idx_iss_items_job_item');
            }
            if (!Schema::hasColumn('issuance_items', 'grade')) $t->string('grade', 5)->nullable()->after('lot_no');
        });
    }

    public function down(): void
    {
        Schema::table('issuance_items', function (Blueprint $t) {
            if (Schema::hasColumn('issuance_items', 'job_item_id')) { $t->dropIndex('idx_iss_items_job_item'); $t->dropColumn('job_item_id'); }
            if (Schema::hasColumn('issuance_items', 'grade')) $t->dropColumn('grade');
        });
    }
};

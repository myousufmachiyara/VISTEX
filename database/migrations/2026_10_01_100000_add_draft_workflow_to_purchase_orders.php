<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PO lifecycle now starts at Draft:
 *   Draft -> Pending (requested) -> Approved -> Issued -> PartiallyReceived -> Received
 *                  \-> Rejected -> (edit) -> Draft
 * Existing rows keep whatever status they already have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('status', 20)->default('Draft')->change();

            if (!Schema::hasColumn('purchase_orders', 'submitted_by')) {
                $table->unsignedBigInteger('submitted_by')->nullable()->after('status');
            }
            if (!Schema::hasColumn('purchase_orders', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreign('submitted_by', 'po_submitted_by_fk')->references('id')->on('users')->onDelete('set null');

            // Bug fix: job_id pointed at Laravel's queue table `jobs` instead of
            // the Sale Order table `job_orders`, so any PO linked to a Sale Order
            // failed its foreign-key check.
            $table->dropForeign('po_job_fk');
            $table->foreign('job_id', 'po_job_fk')->references('id')->on('job_orders')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign('po_submitted_by_fk');
            $table->dropForeign('po_job_fk');
            $table->foreign('job_id', 'po_job_fk')->references('id')->on('jobs')->onDelete('set null');
            $table->dropColumn(['submitted_by', 'submitted_at']);
            $table->string('status', 20)->default('Pending')->change();
        });
    }
};

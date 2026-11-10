<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QC simplification:
 *  - each QC line gets a status (Pass / Fail / Hold / Reprocess) + status remarks
 *  - every status change is kept in qc_status_logs
 *  - tare kg / net kg / quality claim are no longer used (columns kept for old records)
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_inspection_items', function (Blueprint $t) {
            if (!Schema::hasColumn('qc_inspection_items', 'status')) $t->string('status', 20)->nullable()->after('measured_meters');
            if (!Schema::hasColumn('qc_inspection_items', 'status_remarks')) $t->text('status_remarks')->nullable()->after('status');
        });

        if (!Schema::hasTable('qc_status_logs')) {
            Schema::create('qc_status_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('qc_inspection_id');
                $t->unsignedBigInteger('qc_inspection_item_id')->nullable();
                $t->string('from_status', 20)->nullable();
                $t->string('to_status', 20);
                $t->text('remarks')->nullable();
                $t->unsignedBigInteger('changed_by')->nullable();
                $t->timestamps();
                $t->index(['qc_inspection_id', 'created_at']);
                $t->foreign('qc_inspection_id')->references('id')->on('qc_inspections')->cascadeOnDelete();
            });
        }

        // QCs already closed under the old screen: mark their lines Pass so they read sensibly
        \DB::table('qc_inspection_items as i')->join('qc_inspections as q', 'q.id', '=', 'i.qc_inspection_id')
            ->where('q.status', 'Closed')->whereNull('i.status')
            ->update(['i.status' => 'Pass']);
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_status_logs');
        Schema::table('qc_inspection_items', function (Blueprint $t) {
            foreach (['status', 'status_remarks'] as $c) if (Schema::hasColumn('qc_inspection_items', $c)) $t->dropColumn($c);
        });
    }
};

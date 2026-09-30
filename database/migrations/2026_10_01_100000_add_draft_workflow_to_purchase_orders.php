<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Log, Schema};

/**
 * PO lifecycle now starts at Draft:
 *   Draft -> Pending (requested) -> Approved -> Issued -> PartiallyReceived -> Received
 *                  \-> Rejected -> (edit) -> Draft
 * Existing rows keep whatever status they already have.
 *
 * Written to be re-runnable: MySQL does not roll back DDL, so if an earlier
 * attempt stopped half-way every step below checks the live schema first.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Draft becomes the default status
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('status', 20)->default('Draft')->change();
        });

        // 2. Who submitted the PO for approval, and when
        if (!Schema::hasColumn('purchase_orders', 'submitted_by')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('submitted_by')->nullable()->after('status');
            });
        }
        if (!Schema::hasColumn('purchase_orders', 'submitted_at')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            });
        }
        if (!$this->foreignKeyOn('purchase_orders', 'submitted_by')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreign('submitted_by', 'po_submitted_by_fk')->references('id')->on('users')->onDelete('set null');
            });
        }

        // 3. Bug fix: purchase_orders.job_id referenced Laravel's queue table `jobs`
        //    instead of the Sale Order table `job_orders`. Sale Orders / Processing
        //    POs are still locked, so if this repair cannot be applied on a given
        //    server it is logged and skipped rather than blocking the deploy.
        try {
            $this->repointJobForeignKey();
        } catch (\Throwable $e) {
            Log::warning('[Migration] Could not repoint purchase_orders.job_id to job_orders: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        if ($fk = $this->foreignKeyOn('purchase_orders', 'submitted_by')) {
            Schema::table('purchase_orders', fn(Blueprint $t) => $t->dropForeign($fk));
        }
        $drop = array_values(array_filter(['submitted_by', 'submitted_at'], fn($c) => Schema::hasColumn('purchase_orders', $c)));
        if ($drop) {
            Schema::table('purchase_orders', fn(Blueprint $t) => $t->dropColumn($drop));
        }
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('status', 20)->default('Pending')->change();
        });
        // job_id is intentionally left pointing at job_orders — the old target was a bug.
    }

    private function repointJobForeignKey(): void
    {
        if (!Schema::hasColumn('purchase_orders', 'job_id') || !Schema::hasTable('job_orders')) return;

        $current = $this->foreignKeyOn('purchase_orders', 'job_id');
        if ($current && $this->referencedTable('purchase_orders', $current) === 'job_orders') return; // already fixed

        if ($current) {
            Schema::table('purchase_orders', fn(Blueprint $t) => $t->dropForeign($current));
        }

        // Any job_id that is not a real Sale Order would make the new key fail
        DB::table('purchase_orders')
            ->whereNotNull('job_id')
            ->whereNotIn('job_id', DB::table('job_orders')->select('id'))
            ->update(['job_id' => null]);

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreign('job_id', 'po_job_fk')->references('id')->on('job_orders')->onDelete('set null');
        });
    }

    // Name of the foreign key on $table.$column in the live database, whatever it is called
    private function foreignKeyOn(string $table, string $column): ?string
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');
    }

    private function referencedTable(string $table, string $constraint): ?string
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->value('REFERENCED_TABLE_NAME');
    }
};

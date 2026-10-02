<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Each cheque under a PDC carries its own date, so one obligation can be paid
 * in instalments (e.g. 3 cheques dated 1st, 15th, 30th). Existing cheques take
 * their PDC's due date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pdc_cheques', 'cheque_date')) {
            Schema::table('pdc_cheques', function (Blueprint $table) {
                $table->date('cheque_date')->nullable()->after('cheque_no');
                $table->index('cheque_date', 'idx_pc_cheque_date');
            });
        }
        DB::statement('UPDATE pdc_cheques c JOIN pdcs p ON p.id = c.pdc_id SET c.cheque_date = p.due_date WHERE c.cheque_date IS NULL');
    }

    public function down(): void
    {
        Schema::table('pdc_cheques', function (Blueprint $table) {
            $table->dropIndex('idx_pc_cheque_date');
            $table->dropColumn('cheque_date');
        });
    }
};

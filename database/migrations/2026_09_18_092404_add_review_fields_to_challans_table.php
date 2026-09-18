<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            if (!Schema::hasColumn('challans', 'has_objection')) {
                $table->boolean('has_objection')->default(false)->after('status');
            }
            if (!Schema::hasColumn('challans', 'objection_remarks')) {
                $table->text('objection_remarks')->nullable()->after('has_objection');
            }
            if (!Schema::hasColumn('challans', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('objection_remarks');
            }
            if (!Schema::hasColumn('challans', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });

        Schema::table('challans', function (Blueprint $table) {
            if (Schema::hasColumn('challans', 'reviewed_by')) {
                $table->foreign('reviewed_by', 'chl_reviewed_by_fk')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            $table->dropForeign('chl_reviewed_by_fk');
            $table->dropColumn(['has_objection', 'objection_remarks', 'reviewed_by', 'reviewed_at']);
        });
    }
};
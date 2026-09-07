<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'ntn_number')) {
                $table->string('ntn_number', 50)->nullable()->after('name');
            }
        });

        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'ntn_number')) {
                $table->string('ntn_number', 50)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('ntn_number');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('ntn_number');
        });
    }
};
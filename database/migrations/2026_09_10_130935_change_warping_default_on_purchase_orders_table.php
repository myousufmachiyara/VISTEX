<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE purchase_orders MODIFY warping DECIMAL(10,4) NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE purchase_orders MODIFY warping DECIMAL(10,4) NULL DEFAULT 1');
    }
};
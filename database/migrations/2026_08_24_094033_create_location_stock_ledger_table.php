<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 30)->nullable();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('product_id');
            $table->string('status', 20)->default('fresh'); // fresh | issued | leftover
            $table->decimal('quantity', 15, 3); // signed: +in, -out
            $table->decimal('amount', 15, 2)->default(0); // signed value
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->date('entry_date');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('location_id', 'lsl_location_fk')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('product_id', 'lsl_product_fk')->references('id')->on('products')->onDelete('cascade');

            $table->index(['location_id', 'product_id', 'status'], 'idx_lsl_lookup');
            $table->index(['reference_type', 'reference_id'], 'idx_lsl_reference');
        });
    }

    public function down(): void { Schema::dropIfExists('location_stock_ledger'); }
};
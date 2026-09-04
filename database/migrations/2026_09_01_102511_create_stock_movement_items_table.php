<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_movement_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 3);
            $table->decimal('amount', 15, 2)->default(0); // valued at weighted average cost at source, for ledger purposes
            $table->timestamps();

            $table->foreign('stock_movement_id', 'smi_movement_fk')->references('id')->on('stock_movements')->onDelete('cascade');
            $table->foreign('product_id', 'smi_product_fk')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void { Schema::dropIfExists('stock_movement_items'); }
};
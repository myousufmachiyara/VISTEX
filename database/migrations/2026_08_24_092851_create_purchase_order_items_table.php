<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('forecast_id')->nullable(); // which forecast's shortfall this line covers, if any

            $table->decimal('quantity', 15, 3);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);

            $table->timestamps();

            $table->foreign('purchase_order_id', 'poi_order_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('product_id', 'poi_product_fk')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('forecast_id', 'poi_forecast_fk')->references('id')->on('forecasts')->onDelete('set null');

            $table->index('purchase_order_id', 'idx_poi_order');
        });
    }

    public function down(): void { Schema::dropIfExists('purchase_order_items'); }
};
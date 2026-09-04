<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('greige_receive_yarn_consumed', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('greige_receive_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 3);
            $table->decimal('rate', 15, 4);
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('greige_receive_id', 'gryc_receive_fk')->references('id')->on('greige_receives')->onDelete('cascade');
            $table->foreign('product_id', 'gryc_product_fk')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void { Schema::dropIfExists('greige_receive_yarn_consumed'); }
};
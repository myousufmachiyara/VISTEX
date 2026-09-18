<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('challan_id');
            $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description')->nullable();

            $table->decimal('expected_qty', 15, 3)->default(0);
            $table->decimal('received_qty', 15, 3)->default(0);

            $table->string('decision', 20)->default('pending'); // pending | accepted | rejected
            $table->text('rejection_note')->nullable();

            $table->timestamps();

            $table->foreign('challan_id', 'ci_challan_fk')->references('id')->on('challans')->onDelete('cascade');
            $table->foreign('purchase_order_item_id', 'ci_poi_fk')->references('id')->on('purchase_order_items')->onDelete('set null');
            $table->foreign('product_id', 'ci_product_fk')->references('id')->on('products')->onDelete('set null');

            $table->index('challan_id', 'idx_ci_challan');
            $table->index('decision', 'idx_ci_decision');
        });
    }

    public function down(): void { Schema::dropIfExists('challan_items'); }
};
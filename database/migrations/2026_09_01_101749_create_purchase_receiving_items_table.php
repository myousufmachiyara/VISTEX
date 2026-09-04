<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_receiving_id');
            $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            $table->unsignedBigInteger('product_id');

            $table->decimal('quantity_received', 15, 3); // what physically arrived, per the category in-charge's physical check
            $table->decimal('quantity_rejected', 15, 3)->default(0); // portion of quantity_received found unacceptable
            $table->decimal('quantity_returned', 15, 3)->default(0); // portion of quantity_rejected actually sent back so far

            $table->decimal('rate', 15, 2)->default(0); // snapshot from PO at approval time — never shown to receiver during physical check
            $table->decimal('amount', 15, 2)->default(0); // (quantity_received - quantity_rejected) * rate

            $table->timestamps();

            $table->foreign('purchase_receiving_id', 'pri_receiving_fk')->references('id')->on('purchase_receivings')->onDelete('cascade');
            $table->foreign('purchase_order_item_id', 'pri_po_item_fk')->references('id')->on('purchase_order_items')->onDelete('cascade');
            $table->foreign('product_id', 'pri_product_fk')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void { Schema::dropIfExists('purchase_receiving_items'); }
};
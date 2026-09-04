<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_issue_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('processing_issue_id');
            $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->string('lot_no', 100);
            $table->decimal('quantity', 15, 3);
            $table->decimal('rate', 15, 4);
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('processing_issue_id', 'pii_issue_fk')->references('id')->on('processing_issues')->onDelete('cascade');
            $table->foreign('purchase_order_item_id', 'pii_po_item_fk')->references('id')->on('purchase_order_items')->onDelete('set null');
            $table->foreign('product_id', 'pii_product_fk')->references('id')->on('products')->onDelete('cascade');

            $table->index('lot_no', 'idx_pii_lot');
        });
    }

    public function down(): void { Schema::dropIfExists('processing_issue_items'); }
};
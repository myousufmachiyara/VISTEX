<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yarn_in_process_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id'); // weaving-type PO
            $table->unsignedBigInteger('vendor_id'); // the weaving mill
            $table->unsignedBigInteger('product_id'); // yarn product (warp or weft)
            $table->decimal('quantity', 15, 3); // signed — +issued, -consumed
            $table->decimal('amount', 15, 2);   // signed value
            $table->string('reference_type', 30);
            $table->unsignedBigInteger('reference_id');
            $table->date('entry_date');
            $table->timestamps();

            $table->foreign('purchase_order_id', 'yipl_po_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('vendor_id', 'yipl_vendor_fk')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('product_id', 'yipl_product_fk')->references('id')->on('products')->onDelete('cascade');

            $table->index(['purchase_order_id', 'product_id'], 'idx_yipl_po_product');
            $table->index('vendor_id', 'idx_yipl_vendor');
        });
    }

    public function down(): void { Schema::dropIfExists('yarn_in_process_ledger'); }
};